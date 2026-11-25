<?php

namespace App\Http\Controllers;

use App\DTOs\LibrarySettingsData;
use App\DTOs\Queries\LibraryListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Http\Requests\StoreLibraryRequest;
use App\Http\Requests\UpdateLibraryRequest;
use App\Http\Requests\UpdateLibrarySettingsRequest;
use App\Http\Resources\LibraryResource;
use App\Models\Library;
use App\Models\LibrarySettings;
use App\Services\AuthorizationService;
use App\Services\LibraryMembershipService;
use App\Services\Queries\LibraryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LibrariesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, LibraryResource>|LibraryResource[]
     */
    public function index(
        Request $request,
        AuthorizationService $auth,
        LibraryQueryService $libraries,
    ): AnonymousResourceCollection|array {
        $dto = new LibraryListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('search')->toString() ?: null,
            $request->boolean('deleted'),
            $request->integer('per_page', 25),
        );

        $query = $libraries->build($dto, $request->user());

        $permissions = $auth->collectionPermissions($request->user(), Library::class);

        if ($request->boolean('all')) {
            return LibraryResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return LibraryResource::collection(
            $query->paginate($dto->perPage)->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreLibraryRequest $request): LibraryResource
    {
        $library = Library::create($request->validated());

        return new LibraryResource($library->load(['place.region', 'settings']));
    }

    public function show(Library $library): LibraryResource
    {
        abort_if($library->deleted, 404);

        return new LibraryResource($library->load(['place.region', 'settings']));
    }

    public function update(UpdateLibraryRequest $request, Library $library): LibraryResource
    {
        abort_if($library->deleted, 404);

        $library->update($request->validated());

        return new LibraryResource($library->load(['place.region']));
    }

    public function destroy(Library $library, LibraryMembershipService $memberships): JsonResponse
    {
        abort_if($library->deleted, 404);

        $memberships->deactivateLibrary($library);

        return response()->json(status: 204);
    }

    public function restore(Library $library, LibraryMembershipService $memberships): JsonResponse
    {
        abort_unless($library->deleted, 404);

        $memberships->restoreLibrary($library);

        return response()->json(status: 204);
    }

    public function settings(Library $library): JsonResponse
    {
        abort_if($library->deleted, 404);

        $settings = $library->settings()->firstOrCreate([], LibrarySettings::defaults());

        return response()->json([
            'data' => LibrarySettingsData::fromSettings($settings)->toArray(),
        ]);
    }

    public function updateSettings(UpdateLibrarySettingsRequest $request, Library $library): JsonResponse
    {
        abort_if($library->deleted, 404);

        $settings = $library->settings()->firstOrCreate([], LibrarySettings::defaults());

        $data = $request->validated();

        if (! array_key_exists('lend_days', $data)) {
            unset($data['lend_days']);
        }

        if (! array_key_exists('overdue_price_per_day', $data)) {
            unset($data['overdue_price_per_day']);
        }

        $settings->update($data);

        return response()->json([
            'data' => LibrarySettingsData::fromSettings($settings)->toArray(),
        ]);
    }
}
