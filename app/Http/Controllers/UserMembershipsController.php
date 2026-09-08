<?php

namespace App\Http\Controllers;

use App\Models\Library;
use App\Models\User;
use App\Services\ActiveLibraryService;
use App\Services\LibraryMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $actor = $request->user();

        if (! $actor->isSuperAdmin()) {
            $managed = $actor->libraries()->pluck('libraries.id')->all();

            foreach ($data['library_ids'] as $libraryId) {
                if (! in_array((int) $libraryId, $managed, true)) {
                    return response()->json(['message' => __('validation.custom.library_not_managed')], 422);
                }
            }
        }

        $memberships->removeMemberships($user, $data['library_ids']);

        return response()->json(status: 204);
    }

    /**
     * Bulk deaktivacija članstava u aktivnoj biblioteci.
     */
    public function bulkDeactivate(Request $request, LibraryMembershipService $memberships): JsonResponse
    {
        return $this->bulkMemberships($request, $memberships, 'deactivate');
    }

    /**
     * Bulk aktivacija članstava u aktivnoj biblioteci.
     */
    public function bulkActivate(Request $request, LibraryMembershipService $memberships): JsonResponse
    {
        return $this->bulkMemberships($request, $memberships, 'activate');
    }

    /**
     * Bulk trajno uklanjanje članstava u aktivnoj biblioteci.
     */
    public function bulkRemove(Request $request, LibraryMembershipService $memberships): JsonResponse
    {
        return $this->bulkMemberships($request, $memberships, 'remove');
    }

    private function bulkMemberships(Request $request, LibraryMembershipService $memberships, string $operation): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $actor = $request->user();
        $library = app(ActiveLibraryService::class)->resolve($actor);

        if (! $library instanceof Library) {
            return response()->json(['message' => __('validation.custom.active_library_required')], 422);
        }

        $libraryId = (int) $library->id;

        if (! $actor->isSuperAdmin() && ! $actor->managesLibrary($libraryId)) {
            return response()->json(['message' => __('validation.custom.library_not_managed')], 422);
        }

        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'])));

        if ($operation !== 'remove') {
            $eligible = $operation === 'activate'
                ? $this->countWithDeactivatedMembership($userIds, $libraryId)
                : $this->countWithActiveMembership($userIds, $libraryId);

            if ($eligible === 0) {
                return response()->json(['message' => __('validation.custom.memberships_none_eligible')], 422);
            }
        }

        $users = User::whereIn('id', $userIds)->get();

        DB::transaction(function () use ($users, $libraryId, $memberships, $operation) {
            foreach ($users as $user) {
                match ($operation) {
                    'deactivate' => $memberships->deactivateMemberships($user, [$libraryId]),
                    'activate' => $memberships->activateMemberships($user, [$libraryId]),
                    'remove' => $memberships->removeMemberships($user, [$libraryId]),
                };
            }
        });

        return response()->json(status: 204);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function countWithActiveMembership(array $userIds, int $libraryId): int
    {
        return User::whereIn('id', $userIds)
            ->whereHas('libraries', fn ($q) => $q->whereKey($libraryId))
            ->count();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function countWithDeactivatedMembership(array $userIds, int $libraryId): int
    {
        return User::whereIn('id', $userIds)
            ->whereHas('librariesWithTrashed', function ($q) use ($libraryId) {
                $q->whereKey($libraryId)->whereNotNull('library_user.deleted_at');
            })
            ->count();
    }
}
