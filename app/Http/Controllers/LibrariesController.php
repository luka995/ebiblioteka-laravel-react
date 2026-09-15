<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLibraryRequest;
use App\Http\Requests\UpdateLibraryRequest;
use App\Http\Resources\LibraryResource;
use App\Models\Library;
use App\Queries\LibraryFilters;
use App\Services\AuthorizationService;
use App\Services\LibraryMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LibrariesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, LibraryResource>|LibraryResource[]
     */
    public function index(Request $request, AuthorizationService $auth): AnonymousResourceCollection|array
    {
        $libraries = Library::query()
            ->with(['place.region'])
            ->when(! $request->user()->isSuperAdmin(), function ($query) use ($request) {
                $query->whereIn('id', $request->user()->libraries()->pluck('libraries.id'));
            })
            ->when(! $request->boolean('deleted'), fn ($query) => $query->where('deleted', false))
            ->when(
                $request->filled('search'),
                fn ($query) => (new LibraryFilters)->apply($query, $request->only('search'))
            )
            ->latest()
            ->orderByDesc('id');

        $permissions = $auth->collectionPermissions($request->user(), Library::class);

        if ($request->boolean('all')) {
            return LibraryResource::collection($libraries->get())->additional(['permissions' => $permissions]);
        }

        return LibraryResource::collection(
            $libraries->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
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
}
