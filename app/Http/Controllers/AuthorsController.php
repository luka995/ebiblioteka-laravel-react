<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\AuthorListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\Queries\AuthorQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuthorsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, AuthorResource>|AuthorResource[]
     */
    public function index(
        Request $request,
        AuthorizationService $auth,
        ActiveLibraryService $activeLibrary,
        AuthorQueryService $authors,
    ): AnonymousResourceCollection|array {
        $user = $request->user();
        $active = $activeLibrary->resolve($user);

        $dto = new AuthorListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('search')->toString() ?: null,
            $request->filled('library_id') ? $request->integer('library_id') : null,
            $request->integer('per_page', 25),
        );

        $query = $authors->build($dto, $user, $active);

        $permissions = $auth->collectionPermissions($user, Author::class);

        if ($request->boolean('all')) {
            return AuthorResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return AuthorResource::collection(
            $query->paginate($dto->perPage)->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreAuthorRequest $request): AuthorResource
    {
        $author = Author::create($request->validated());

        return new AuthorResource($author->load('library'));
    }

    public function update(UpdateAuthorRequest $request, Author $author): AuthorResource
    {
        $author->update($request->validated());

        return new AuthorResource($author->load('library'));
    }

    public function destroy(Request $request, Author $author, ActiveLibraryService $activeLibrary): JsonResponse
    {
        $this->authorizeLibraryScope($request, $author, $activeLibrary);

        if ($author->books()->withTrashed()->exists()) {
            return response()->json(['message' => __('validation.custom.author_has_books')], 422);
        }

        $author->delete();

        return response()->json(status: 204);
    }

    private function authorizeLibraryScope(Request $request, Author $author, ActiveLibraryService $activeLibrary): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        $active = $activeLibrary->resolve($request->user());

        abort_unless($active && $author->library_id === $active->id, 404);
    }
}
