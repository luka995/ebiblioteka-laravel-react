<?php

namespace App\Http\Controllers;

use App\Enums\LibraryRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => array_map(
                fn (LibraryRole $role) => ['value' => $role->value, 'label' => $role->getLabel()],
                LibraryRole::cases()
            ),
        ]);
    }

    public function assignable(Request $request): JsonResponse
    {
        $actor = $request->user();

        $roles = $actor->isSuperAdmin()
            ? LibraryRole::cases()
            : ($actor->activeRole() === null
                ? []
                : LibraryRole::assignableBy($actor->activeRole()));

        return response()->json([
            'roles' => array_map(
                fn (LibraryRole $role) => ['value' => $role->value, 'label' => $role->getLabel()],
                $roles
            ),
        ]);
    }
}
