<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\RegionListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Services\AuthorizationService;
use App\Services\Queries\RegionQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, RegionResource>|RegionResource[]
     */
    public function index(Request $request, AuthorizationService $auth, RegionQueryService $regions): AnonymousResourceCollection|array
    {
        $dto = new RegionListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('search')->toString() ?: null,
            $request->integer('per_page', 25),
        );

        $query = $regions->build($dto);

        $permissions = $auth->collectionPermissions($request->user(), Region::class);

        if ($request->boolean('all')) {
            return RegionResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return RegionResource::collection(
            $query->paginate($dto->perPage)->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreRegionRequest $request): RegionResource
    {
        $region = Region::create($request->validated());

        return new RegionResource($region->loadCount('places'));
    }

    public function update(UpdateRegionRequest $request, Region $region): RegionResource
    {
        $region->update($request->validated());

        return new RegionResource($region->loadCount('places'));
    }

    public function destroy(Region $region): JsonResponse
    {
        if ($region->places()->exists()) {
            return response()->json(['message' => __('validation.custom.region_has_places')], 422);
        }

        $region->delete();

        return response()->json(status: 204);
    }
}
