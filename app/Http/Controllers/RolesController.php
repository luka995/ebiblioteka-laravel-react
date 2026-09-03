<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => array_map(
                fn (UserRole $role) => ['value' => $role->value, 'label' => $role->getLabel()],
                UserRole::cases()
            ),
        ]);
    }

    public function assignable(Request $request): JsonResponse
    {
        $actor = $request->user()->role;

        return response()->json([
            'roles' => array_map(
                fn (UserRole $role) => ['value' => $role->value, 'label' => $role->getLabel()],
                UserRole::assignableBy($actor)
            ),
        ]);
    }
}
