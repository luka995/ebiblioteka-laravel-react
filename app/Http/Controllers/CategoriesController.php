<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\CategoryListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Book;
use App\Models\Category;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\CategoryResolverService;
use App\Services\Queries\CategoryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoriesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, CategoryResource>|CategoryResource[]
     */
    public function index(
        Request $request,
        AuthorizationService $auth,
        ActiveLibraryService $activeLibrary,
        CategoryQueryService $categories,
    ): AnonymousResourceCollection|array {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $dto = new CategoryListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('search')->toString() ?: null,
            $request->filled('library_id') ? $request->integer('library_id') : null,
            $request->filled('parent_id') ? $request->integer('parent_id') : null,
            $request->integer('per_page', 25),
        );

        $query = $categories->build($dto, $user, $library);

        $permissions = $auth->collectionPermissions($user, Category::class);

        if ($request->boolean('all')) {
            return CategoryResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return CategoryResource::collection(
            $query->paginate($dto->perPage)->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreCategoryRequest $request, CategoryResolverService $categories): CategoryResource
    {
        $category = $categories->findOrCreate($request->validated());

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

        $isUsedByBooks = Book::withTrashed()
            ->where(function ($query) use ($category): void {
                $query->where('category_primary_id', $category->id)
                    ->orWhere('category_secondary_id', $category->id);
            })
            ->exists();

        if ($isUsedByBooks) {
            return response()->json(['message' => __('validation.custom.category_has_books')], 422);
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
