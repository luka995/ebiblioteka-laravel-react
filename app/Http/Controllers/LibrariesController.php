<?php

namespace App\Http\Controllers;

use App\Http\Resources\LibraryResource;
use App\Models\Library;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LibrariesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, LibraryResource>|LibraryResource[]
     */
    public function index(Request $request): AnonymousResourceCollection|array
    {
        $libraries = Library::query()
            ->with(['place.region'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->string('search'));
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'ilike', "%{$term}%")
                        ->orWhere('address', 'ilike', "%{$term}%")
                        ->orWhereHas('place', fn ($place) => $place->where('name', 'ilike', "%{$term}%"));
                });
            })
            ->latest();

        if ($request->boolean('all')) {
            return LibraryResource::collection($libraries->get());
        }

        return LibraryResource::collection(
            $libraries->paginate($request->integer('per_page', 25))->withQueryString()
        );
    }

    public function store(Request $request): LibraryResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'place_id' => ['required', 'integer', Rule::exists('places', 'id')],
            'work_time' => ['nullable', 'string', 'max:255'],
        ]);

        $library = Library::create($data);

        return new LibraryResource($library->load(['place.region']));
    }
}
