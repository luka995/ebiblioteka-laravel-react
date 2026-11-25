<?php

namespace App\Http\Controllers;

use App\Models\Borrow;
use App\Services\ActiveLibraryService;
use App\Services\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(
        Request $request,
        ActiveLibraryService $activeLibrary,
        DashboardStatsService $stats,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor->can('viewAny', Borrow::class), 403);

        $library = $activeLibrary->requireActiveLibrary($actor);

        abort_unless($actor->isSuperAdmin() || $actor->isStaffIn($library->id), 403);

        $library->loadMissing('settings');

        return response()->json([
            'data' => $stats->forLibrary($library),
        ]);
    }
}
