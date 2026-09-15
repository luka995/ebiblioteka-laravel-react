<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Queries\TagFilters;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, TagResource>|TagResource[]
     */
    public function index(Request $request, AuthorizationService $auth): AnonymousResourceCollection|array
    {
        $query = Tag::query()
            ->with('library')
            ->withCount('users')
            ->when(! $request->user()->isSuperAdmin(), function ($q) use ($request) {
                $q->whereIn('library_id', $request->user()->libraries()->pluck('libraries.id'));
            });

        (new TagFilters)->apply($query, $request->only(['search', 'library_id']));

        $query->orderBy('name')->orderBy('id');

        $permissions = $auth->collectionPermissions($request->user(), Tag::class);

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
