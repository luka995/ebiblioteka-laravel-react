<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Queries\TagFilters;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, TagResource>|TagResource[]
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection|array
    {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $query = Tag::query()
            ->with('library')
            ->withCount('users');

        $requestedLibrary = $request->filled('library_id') ? $request->integer('library_id') : null;

        if ($user->isSuperAdmin()) {
            // Eksplicitni filter ima prednost, inace aktivna biblioteka.
            $query->where('library_id', $requestedLibrary ?? $library->id);
        } else {
            $query->where('library_id', $library->id);
        }

        (new TagFilters)->apply($query, $request->only(['search']));

        $query->orderBy('name')->orderBy('id');

        $permissions = $auth->collectionPermissions($user, Tag::class);

        if ($request->boolean('all')) {
            return TagResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return TagResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreTagRequest $request): TagResource
    {
        $tag = Tag::create($request->validated());

        return new TagResource($tag->load('library')->loadCount('users'));
    }

    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return new TagResource($tag->load('library')->loadCount('users'));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(status: 204);
    }
}
