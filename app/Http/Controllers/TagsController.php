<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\SortingDTO;
use App\DTOs\Queries\TagListQueryDTO;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\Queries\TagQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, TagResource>|TagResource[]
     */
    public function index(
        Request $request,
        AuthorizationService $auth,
        ActiveLibraryService $activeLibrary,
        TagQueryService $tags,
    ): AnonymousResourceCollection|array {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $dto = new TagListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('search')->toString() ?: null,
            $request->filled('library_id') ? $request->integer('library_id') : null,
            $request->integer('per_page', 25),
        );

        $query = $tags->build($dto, $user, $library);

        $permissions = $auth->collectionPermissions($user, Tag::class);

        if ($request->boolean('all')) {
            return TagResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return TagResource::collection(
            $query->paginate($dto->perPage)->withQueryString()
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
