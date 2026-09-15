<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoriesController extends Controller
{
    use AppliesTransliteratedSearch;

    /**
     * @return AnonymousResourceCollection<int, CategoryResource>|CategoryResource[]
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection|array
    {
        $user = $request->user();
        $active = $activeLibrary->resolve($user);

        $query = Category::query()
            ->with('parent', 'library')
            ->withCount('children');

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
            ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', $request->integer('parent_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $this->whereTransliterated($q, 'name', trim((string) $request->string('search')));
            })
            ->orderBy('name');

        $permissions = $auth->collectionPermissions($user, Category::class);

        if ($request->boolean('all')) {
            return CategoryResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return CategoryResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $category = Category::create($request->validated());

        return new CategoryResource($category->load('parent', 'library')->loadCount('children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category->load('parent', 'library')->loadCount('children'));
    }

    public function destroy(Request $request, Category $category, ActiveLibraryService $activeLibrary): JsonResponse
    {
        $this->authorizeLibraryScope($request, $category, $activeLibrary);

        if ($category->children()->exists()) {
            return response()->json(['message' => __('validation.custom.category_has_children')], 422);
        }

        $category->delete();

        return response()->json(status: 204);
    }

    private function authorizeLibraryScope(Request $request, Category $category, ActiveLibraryService $activeLibrary): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        $active = $activeLibrary->resolve($request->user());

        abort_unless($active && $category->library_id === $active->id, 404);
    }
}
