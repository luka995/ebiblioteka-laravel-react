<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuthorsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, AuthorResource>|AuthorResource[]
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection|array
    {
        $user = $request->user();
        $active = $activeLibrary->resolve($user);

        $query = Author::query()->with('library');

        if (! $user->isSuperAdmin()) {
            if (! $active) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('library_id', $active->id);
            }
        } elseif ($request->filled('library_id')) {
            $query->where('library_id', $request->integer('library_id'));
        }

        $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->string('search'));
                $q->where('name', 'ilike', "%{$term}%");
            })
            ->orderBy('name');

        $permissions = $auth->collectionPermissions($user, Author::class);

        if ($request->boolean('all')) {
            return AuthorResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return AuthorResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
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
