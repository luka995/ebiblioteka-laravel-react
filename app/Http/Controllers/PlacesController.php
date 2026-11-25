<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\PlaceListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use App\Services\AuthorizationService;
use App\Services\Queries\PlaceQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlacesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, PlaceResource>|PlaceResource[]
     */
    public function index(Request $request, AuthorizationService $auth, PlaceQueryService $places): AnonymousResourceCollection|array
    {
        $dto = new PlaceListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('search')->toString() ?: null,
            $request->filled('region_id') ? $request->integer('region_id') : null,
            $request->integer('per_page', 25),
        );

        $query = $places->build($dto);

        $permissions = $auth->collectionPermissions($request->user(), Place::class);

        if ($request->boolean('all')) {
            return PlaceResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return PlaceResource::collection(
            $query->paginate($dto->perPage)->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StorePlaceRequest $request): PlaceResource
    {
        $place = Place::create($request->validated());

        return new PlaceResource($place->load('region'));
    }

    public function update(UpdatePlaceRequest $request, Place $place): PlaceResource
    {
        $place->update($request->validated());

        return new PlaceResource($place->load('region'));
    }

    public function destroy(Place $place): JsonResponse
    {
        if ($place->libraries()->exists()) {
            return response()->json(['message' => __('validation.custom.place_has_libraries')], 422);
        }

        $place->delete();

        return response()->json(status: 204);
    }
}
