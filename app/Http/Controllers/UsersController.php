<?php

namespace App\Http\Controllers;

use App\Enums\BarcodePrintFormat;
use App\Http\Requests\PrintUserBarcodeRequest;
use App\Http\Requests\PrintUserBulkBarcodeRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserDetailResource;
use App\Http\Resources\UserResource;
use App\Models\Library;
use App\Models\User;
use App\Queries\UserFilters;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\BarcodePdfService;
use App\Services\LibraryMembershipService;
use App\Services\Mail\UserMailService;
use App\Support\BarCode;
use App\Support\BarCodeImage;
use App\Support\BarcodeLabel;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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
        $user->bar_code = BarCode::generate();
        $user->save();

        $memberships->syncMemberships($user, $data['libraries'] ?? []);

        app(UserMailService::class)->sendAccountCreated($user, $plainPassword);

        return new UserResource($user->load(['libraries', 'librariesWithTrashed', 'tags']));
    }

    public function show(User $user): UserDetailResource
    {
        return new UserDetailResource($user->load(['libraries', 'librariesWithTrashed', 'tags']));
    }

    public function barcode(User $user): Response
    {
        if ($user->bar_code === null || ! BarCode::validate($user->bar_code)) {
            abort(404);
        }

        return response(BarCodeImage::render($user->bar_code), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function printBarcode(PrintUserBarcodeRequest $request, User $user, BarcodePdfService $pdf): Response
    {
        if ($user->bar_code === null || ! BarCode::validate($user->bar_code)) {
            abort(404);
        }

        $format = BarcodePrintFormat::from($request->validated('format'));

        $contents = $pdf->render([new BarcodeLabel($user->bar_code)], $format);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="barkod-%s.pdf"', $user->bar_code),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, LibraryMembershipService $memberships): UserResource
    {
        $data = $request->validated();
        $regenerateBarcode = (bool) ($data['regenerate_barcode'] ?? false);
        unset($data['regenerate_barcode']);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->fill($data);
        $user->name = trim($user->first_name.' '.$user->last_name);

        if ($regenerateBarcode) {
            $user->bar_code = BarCode::generate();
        }

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
     * Bulk regeneracija bar-kodova — superadmin (svi) ili admin biblioteke
     * (samo članovi aktivne biblioteke). Ceo batch je atomski.
     */
    public function bulkRegenerateBarcode(Request $request): JsonResponse
    {
        $userIds = $this->bulkUserIds($request);
        $actor = $request->user();

        if ($error = $this->guardBulkBarcodeScope($actor, $userIds)) {
            return $error;
        }

        DB::transaction(function () use ($userIds) {
            User::whereIn('id', $userIds)->get()->each(function (User $user) {
                $user->bar_code = BarCode::generate();
                $user->save();
            });
        });

        return response()->json(status: 204);
    }

    /**
     * Bulk stampa bar-kodova za izabrane korisnike.
     *
     * PDF se generise iz postojecih bar-kodova (bez regeneracije). Ako bilo koji
     * izabrani korisnik ne postoji ili nema vazeci bar-kod, ceo zahtev se odbija.
     */
    public function bulkPrintBarcode(PrintUserBulkBarcodeRequest $request, BarcodePdfService $pdf): Response|JsonResponse
    {
        $userIds = $request->userIds();
        $actor = $request->user();

        if ($error = $this->guardBulkBarcodeScope($actor, $userIds)) {
            return $error;
        }

        $users = User::whereIn('id', $userIds)
            ->get()
            ->sortBy(fn (User $user) => array_search($user->id, $userIds, true))
            ->values();

        $printable = $users->filter(
            fn (User $user) => $user->bar_code !== null && BarCode::validate($user->bar_code)
        );

        if ($printable->count() !== count($userIds)) {
            return response()->json([
                'message' => __('validation.custom.users_without_barcode', [
                    'count' => count($userIds) - $printable->count(),
                ]),
            ], 422);
        }

        $labels = $printable
            ->map(fn (User $user) => new BarcodeLabel($user->bar_code))
            ->all();

        $format = BarcodePrintFormat::from($request->validated('format'));

        $contents = $pdf->render($labels, $format);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="barkodovi.pdf"',
        ]);
    }

    /**
     * Zajednicka provera opsega za bulk bar-kod operacije: superadmin (svi) ili
     * admin biblioteke (samo clanovi aktivne biblioteke).
     *
     * @param  array<int, int>  $userIds
     */
    private function guardBulkBarcodeScope(User $actor, array $userIds): ?JsonResponse
    {
        if ($actor->isSuperAdmin()) {
            return null;
        }

        $library = app(ActiveLibraryService::class)->resolve($actor);

        if (! $library instanceof Library) {
            return response()->json(['message' => __('validation.custom.active_library_required')], 422);
        }

        $libraryId = (int) $library->id;

        if (! $actor->managesLibrary($libraryId)) {
            return response()->json(['message' => __('validation.custom.library_not_managed')], 422);
        }

        $nonMembers = User::whereIn('id', $userIds)
            ->whereDoesntHave('libraries', fn ($q) => $q->whereKey($libraryId))
            ->exists();

        if ($nonMembers) {
            return response()->json(['message' => __('validation.custom.user_not_library_member')], 422);
        }

        return null;
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
