<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('libraries'));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $data = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $user->fill($data);
        $user->name = trim($user->first_name.' '.$user->last_name);
        $user->save();

        return new UserResource($user->load('libraries'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => Hash::make($request->string('password')),
        ])->save();

        return response()->json(status: 204);
    }
}
