<?php

namespace App\Http\Controllers;

use App\Http\Resources\RegionResource;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, RegionResource>
     */
    public function index(): AnonymousResourceCollection
    {
        return RegionResource::collection(
            Region::withCount('places')->orderBy('name')->get()
        );
    }

    public function store(Request $request): RegionResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:regions,name'],
        ]);

        return new RegionResource(Region::create($data));
    }
}
