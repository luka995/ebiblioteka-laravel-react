<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\BarCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, UserResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('libraries')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->string('search'));
                $query->where(function ($query) use ($term) {
                    $query->where('email', 'ilike', "%{$term}%")
                        ->orWhere('first_name', 'ilike', "%{$term}%")
                        ->orWhere('last_name', 'ilike', "%{$term}%")
                        ->orWhere('username', 'ilike', "%{$term}%")
                        ->orWhere('bar_code', 'ilike', "%{$term}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->latest()
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $data = $request->validated();

        $user = new User($data);
        $user->name = trim($data['first_name'].' '.$data['last_name']);
        $user->password = Hash::make($data['password']);
        $user->bar_code = $data['bar_code'] ?? BarCode::generate();
        $user->save();

        $user->libraries()->sync($data['libraries'] ?? []);

        return new UserResource($user->load('libraries'));
    }

    public function nextBarcode(): JsonResponse
    {
        return response()->json(['bar_code' => BarCode::generate()]);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('libraries'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->fill($data);
        $user->name = trim($user->first_name.' '.$user->last_name);
        $user->save();

        if ($request->has('libraries')) {
            $user->libraries()->sync($data['libraries'] ?? []);
        }

        return new UserResource($user->load('libraries'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return response()->json(['message' => __('validation.custom.cannot_delete_self')], 422);
        }

        $user->delete();

        return response()->json(status: 204);
    }
}
