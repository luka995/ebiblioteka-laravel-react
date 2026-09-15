<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, RegionResource>|RegionResource[]
     */
    public function index(Request $request, AuthorizationService $auth): AnonymousResourceCollection|array
    {
        $query = Region::query()
            ->withCount('places')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->string('search'));
                $q->where('name', 'ilike', "%{$term}%");
            })
            ->orderBy('name')
            ->orderBy('id');

        $permissions = $auth->collectionPermissions($request->user(), Region::class);

        if ($request->boolean('all')) {
            return RegionResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return RegionResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
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
