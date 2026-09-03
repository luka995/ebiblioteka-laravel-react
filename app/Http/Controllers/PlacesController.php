<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlaceResource;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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

    public function store(Request $request): PlaceResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
        ]);

        $request->validate([
            'name' => [
                'required',
                Rule::unique('places', 'name')->where('region_id', $data['region_id']),
            ],
        ], [
            'name.unique' => __('validation.custom.place_duplicate'),
        ]);

        $place = Place::create($data);

        return new PlaceResource($place->load('region'));
    }
}
