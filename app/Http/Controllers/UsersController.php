<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\UserFilters;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\LibraryMembershipService;
use App\Services\Mail\UserMailService;
use App\Support\BarCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, UserResource>
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $actor = $request->user();
        $filters = $request->only([
            'email',
            'username',
            'first_name',
            'last_name',
            'bar_code',
            'jmbg',
            'city',
            'role',
            'library_id',
        ]);

        if (! $actor->isSuperAdmin()) {
            unset($filters['library_id']);
        }

        $query = (new UserFilters)->apply(
            User::query()->with(['libraries', 'librariesWithTrashed']),
            $filters
        );

        if (! ($actor->isSuperAdmin() && ! empty($filters['library_id']))) {
            // Superadmin sa izabranim library_id filterom se ne sužava i na aktivnu
            // biblioteku (filter ima prednost); ostali idu kroz scope po aktivnoj biblioteci.
            $query = $activeLibrary->scopeUsers($query, $actor);
        }

        $users = $query
            ->latest()
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return UserResource::collection($users)->additional([
            'permissions' => $auth->collectionPermissions($request->user(), User::class),
        ]);
    }

    public function store(StoreUserRequest $request, LibraryMembershipService $memberships): UserResource
    {
        $data = $request->validated();
        $plainPassword = $data['password'];

        $user = new User($data);
        $user->name = trim($data['first_name'].' '.$data['last_name']);
        $user->password = Hash::make($plainPassword);
        $user->bar_code = $data['bar_code'] ?? BarCode::generate();
        $user->save();

        $memberships->syncMemberships($user, $data['libraries'] ?? []);

        app(UserMailService::class)->sendAccountCreated($user, $plainPassword);

        return new UserResource($user->load(['libraries', 'librariesWithTrashed', 'tags']));
    }

    public function nextBarcode(): JsonResponse
    {
        return response()->json(['bar_code' => BarCode::generate()]);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['libraries', 'librariesWithTrashed', 'tags']));
    }

    public function update(UpdateUserRequest $request, User $user, LibraryMembershipService $memberships): UserResource
    {
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->fill($data);
        $user->name = trim($user->first_name.' '.$user->last_name);
        $user->save();

        if ($request->has('libraries')) {
            $desired = array_values(array_unique(array_map('intval', $data['libraries'] ?? [])));

            if (! $request->user()->isSuperAdmin()) {
                $managed = $request->user()->libraries()->pluck('libraries.id')->all();
                $kept = $user->libraries()
                    ->whereNotIn('libraries.id', $managed)
                    ->pluck('libraries.id')
                    ->all();

                $desired = array_values(array_unique([...$kept, ...$desired]));
            }

            $memberships->syncMemberships($user, $desired);
        }

        return new UserResource($user->load(['libraries', 'librariesWithTrashed', 'tags']));
    }

    public function updatePassword(UpdateUserPasswordRequest $request, User $user): JsonResponse
    {
        $user->forceFill([
            'password' => Hash::make($request->string('password')),
        ])->save();

        return response()->json(status: 204);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return response()->json(['message' => __('validation.custom.cannot_delete_self')], 422);
        }

        $user->delete();

        return response()->json(status: 204);
    }

    public function forceDestroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return response()->json(['message' => __('validation.custom.cannot_delete_self')], 422);
        }

        try {
            $user->forceDelete();
        } catch (QueryException $e) {
            if (! $this->isForeignKeyViolation($e)) {
                throw $e;
            }

            return response()->json(['message' => __('validation.custom.cannot_force_delete')], 422);
        }

        return response()->json(status: 204);
    }

    /**
     * Bulk soft brisanje (deaktivacija) naloga — superadmin.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $userIds = $this->bulkUserIds($request);

        if (in_array($request->user()->id, $userIds, true)) {
            return response()->json(['message' => __('validation.custom.cannot_delete_self')], 422);
        }

        DB::transaction(function () use ($userIds) {
            User::whereIn('id', $userIds)->get()->each->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * Bulk trajno brisanje naloga — superadmin; FK RESTRICT štiti naloge sa relacijama.
     */
    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $userIds = $this->bulkUserIds($request);

        if (in_array($request->user()->id, $userIds, true)) {
            return response()->json(['message' => __('validation.custom.cannot_delete_self')], 422);
        }

        try {
            DB::transaction(function () use ($userIds) {
                User::whereIn('id', $userIds)->get()->each->forceDelete();
            });
        } catch (QueryException $e) {
            if (! $this->isForeignKeyViolation($e)) {
                throw $e;
            }

            return response()->json(['message' => __('validation.custom.cannot_force_delete')], 422);
        }

        return response()->json(status: 204);
    }

    /**
     * @return array<int, int>
     */
    private function bulkUserIds(Request $request): array
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        return array_values(array_unique(array_map('intval', $data['user_ids'])));
    }

    /**
     * FK ON DELETE RESTRICT (SQLSTATE 23503/23000, SQLITE_CONSTRAINT) štiti nalog
     * sa zabeleženim relacijama (pozajmice, rezervacije, članarine, istorija...).
     */
    private function isForeignKeyViolation(QueryException $e): bool
    {
        $codes = [
            (string) $e->getCode(),
            (string) ($e->errorInfo[0] ?? ''),
            (string) ($e->errorInfo[1] ?? ''),
        ];

        return collect($codes)->contains(fn (string $code) => in_array($code, [
            '23503', // PostgreSQL foreign_key_violation
            '23000', // MySQL ER_NO_REFERENCED_ROW_2 / generic integrity
            '1451',  // MySQL ER_ROW_IS_REFERENCED_2
            '19',    // SQLite SQLITE_CONSTRAINT
            '787',   // SQLite SQLITE_CONSTRAINT_FOREIGNKEY
        ], true));
    }
}
