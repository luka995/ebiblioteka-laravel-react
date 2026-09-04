<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LibraryMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMembershipsController extends Controller
{
    public function deactivate(Request $request, User $user, LibraryMembershipService $memberships): JsonResponse
    {
        $data = $request->validate([
            'library_ids' => ['required', 'array'],
            'library_ids.*' => ['integer'],
        ]);

        $memberships->deactivateMemberships($user, $data['library_ids']);

        return response()->json(status: 204);
    }

    public function activate(Request $request, User $user, LibraryMembershipService $memberships): JsonResponse
    {
        $data = $request->validate([
            'library_ids' => ['required', 'array'],
            'library_ids.*' => ['integer'],
        ]);

        $memberships->activateMemberships($user, $data['library_ids']);

        return response()->json(status: 204);
    }

    public function destroy(Request $request, User $user, LibraryMembershipService $memberships): JsonResponse
    {
        $data = $request->validate([
            'library_ids' => ['required', 'array'],
            'library_ids.*' => ['integer'],
        ]);

        $memberships->removeMemberships($user, $data['library_ids']);

        return response()->json(status: 204);
    }
}
