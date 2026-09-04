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
use App\Support\BarCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, UserResource>
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $query = (new UserFilters)->apply(
            User::query()->with(['libraries', 'librariesWithTrashed']),
            $request->only([
                'email',
                'username',
                'first_name',
                'last_name',
                'bar_code',
                'jmbg',
                'city',
                'role',
                'library_id',
            ])
        );

        $query = $activeLibrary->scopeUsers($query, $request->user());

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

        $user = new User($data);
        $user->name = trim($data['first_name'].' '.$data['last_name']);
        $user->password = Hash::make($data['password']);
        $user->bar_code = $data['bar_code'] ?? BarCode::generate();
        $user->save();

        $memberships->syncMemberships($user, $data['libraries'] ?? []);

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
            $memberships->syncMemberships($user, $data['libraries'] ?? []);
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
        } catch (QueryException) {
            return response()->json(['message' => __('validation.custom.cannot_force_delete')], 422);
        }

        return response()->json(status: 204);
    }
}
