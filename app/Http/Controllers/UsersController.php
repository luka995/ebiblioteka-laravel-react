<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Enums\UserRole;
use App\Support\BarCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function store(Request $request): UserResource
    {
        $actorRole = $request->user()->role;

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'jmbg' => ['nullable', 'string', 'digits:13'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'post_code' => ['nullable', 'string', 'max:20'],
            'bar_code' => ['nullable', 'string', 'digits:13', 'unique:users,bar_code'],
            'libraries' => ['nullable', 'array'],
            'libraries.*' => ['integer', Rule::exists('libraries', 'id')],
        ]);

        $assignable = UserRole::assignableBy($actorRole);
        $requestedRole = UserRole::from($data['role']);

        if (! in_array($requestedRole, $assignable, true)) {
            throw ValidationException::withMessages([
                'role' => [__('validation.role_not_assignable')],
            ]);
        }

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
}
