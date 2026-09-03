<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlacesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, PlaceResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return PlaceResource::collection(
            Place::query()
                ->with('region')
                ->when($request->filled('region_id'), fn ($query) => $query->where('region_id', $request->integer('region_id')))
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StorePlaceRequest $request): PlaceResource
    {
        $place = Place::create($request->validated());

        return new PlaceResource($place->load('region'));
    }
}
