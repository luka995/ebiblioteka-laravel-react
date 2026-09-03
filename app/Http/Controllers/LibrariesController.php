<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLibraryRequest;
use App\Http\Requests\UpdateLibraryRequest;
use App\Http\Resources\LibraryResource;
use App\Models\Library;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LibrariesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, LibraryResource>|LibraryResource[]
     */
    public function index(Request $request): AnonymousResourceCollection|array
    {
        $libraries = Library::query()
            ->with(['place.region'])
            ->where('deleted', false)
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

    public function store(StoreLibraryRequest $request): LibraryResource
    {
        $library = Library::create($request->validated());

        return new LibraryResource($library->load(['place.region']));
    }

    public function show(Library $library): LibraryResource
    {
        abort_if($library->deleted, 404);

        return new LibraryResource($library->load(['place.region']));
    }

    public function update(UpdateLibraryRequest $request, Library $library): LibraryResource
    {
        abort_if($library->deleted, 404);

        $library->update($request->validated());

        return new LibraryResource($library->load(['place.region']));
    }

    public function destroy(Library $library): JsonResponse
    {
        abort_if($library->deleted, 404);

        $library->update(['deleted' => true]);

        return response()->json(status: 204);
    }
}
