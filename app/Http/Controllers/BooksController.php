<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookCopyResource;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\Library;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\BookCopyService;
use App\Services\BookService;
use App\Services\Isbn\BookMatcher;
use App\Services\Isbn\IsbnLookupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BooksController extends Controller
{
    use AppliesTransliteratedSearch;

    /**
     * @return AnonymousResourceCollection<int, BookResource>
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $user = $request->user();
        $active = $activeLibrary->resolve($user);

        $query = Book::query()
            ->with(['library', 'categoryPrimary', 'categorySecondary', 'authors']);
        $this->withAvailabilityCounts($query);

        if (! $user->isSuperAdmin()) {
            if (! $active) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('library_id', $active->id);
            }
        } elseif ($request->filled('library_id')) {
            $query->where('library_id', $request->integer('library_id'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->string('search'));
            $this->whereTransliterated($query, 'name', $term);
        }

        if ($request->filled('category_id')) {
            $categoryId = $request->integer('category_id');
            $query->where(function ($group) use ($categoryId): void {
                $group->where('category_primary_id', $categoryId)
                    ->orWhere('category_secondary_id', $categoryId);
            });
        }

        $query->orderBy('name')->orderBy('id');

        $permissions = $auth->collectionPermissions($user, Book::class);

        if ($request->boolean('all')) {
            return BookResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return BookResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function isbnLookup(Request $request, IsbnLookupService $lookup, ActiveLibraryService $activeLibrary): JsonResponse
    {
        $validated = $request->validate(['isbn' => ['required', 'string', 'max:32']]);
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $library = $request->filled('library_id')
                ? Library::findOrFail($request->integer('library_id'))
                : $activeLibrary->resolve($user);
        } else {
            $library = $activeLibrary->resolve($user);
        }

        if ($library === null) {
            return response()->json(['message' => __('validation.custom.active_library_required')], 422);
        }

        $result = $lookup->search($library, (string) $validated['isbn']);

        if ($result === null) {
            return response()->json(['message' => __('validation.custom.book_isbn_invalid')], 422);
        }

        $book = $result['book'] === null ? null : $this->loadForResource($result['book']);

        return response()->json([
            'source' => $result['source'],
            'isbn' => $result['isbn'],
            'metadata' => $result['metadata']?->toArray(),
            'book' => $book === null ? null : (new BookResource($book))->resolve($request),
            'existing_copies' => BookCopyResource::collection($result['existing_copies'])->resolve($request),
            'matches' => BookResource::collection($result['matches'])->resolve($request),
        ]);
    }

    public function duplicateCheck(Request $request, BookMatcher $matcher, ActiveLibraryService $activeLibrary): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'authors' => ['sometimes', 'array'],
            'authors.*' => ['nullable', 'string', 'max:255'],
            'library_id' => ['sometimes', 'integer'],
        ]);

        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $library = $request->filled('library_id')
                ? Library::find($request->integer('library_id'))
                : $activeLibrary->resolve($user);
        } else {
            $library = $activeLibrary->resolve($user);
        }

        if ($library === null) {
            return response()->json(['matches' => []]);
        }

        $matches = $matcher->duplicates(
            $library,
            (string) $validated['name'],
            $validated['authors'] ?? [],
        );

        return response()->json([
            'matches' => BookResource::collection($matches)->resolve($request),
        ]);
    }

    public function uploadCover(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('image')->store('books/covers', 'public');

        return response()->json([
            'path' => $path,
            'url' => parse_url(Storage::disk('public')->url($path), PHP_URL_PATH),
        ]);
    }

    public function store(StoreBookRequest $request, BookService $books, BookCopyService $copies, BookMatcher $matcher): BookResource|JsonResponse
    {
        $data = $request->validated();
        $withCopies = (bool) ($data['with_copies'] ?? false);

        if (! (bool) ($data['confirm_duplicate'] ?? false)) {
            $library = Library::find($data['library_id']);
            $duplicates = $library === null
                ? collect()
                : $matcher->duplicates($library, $data['name'], $data['authors'] ?? []);

            if ($duplicates->isNotEmpty()) {
                return response()->json([
                    'message' => __('validation.custom.book_duplicate'),
                    'duplicate_books' => BookResource::collection($duplicates)->resolve($request),
                ], 409);
            }
        }

        $bookData = Arr::only($data, [
            'library_id', 'name', 'category_primary_id', 'category_secondary_id',
            'description', 'image', 'cover_url', 'authors',
        ]);

        $book = DB::transaction(function () use ($books, $copies, $bookData, $data, $withCopies): Book {
            $book = $books->create($bookData);

            if ($withCopies) {
                $copies->createForBook($book, $data);
            }

            return $book;
        });

        return new BookResource($this->loadForResource($book));
    }

    public function show(Book $book): BookResource
    {
        return new BookResource($this->loadForResource($book));
    }

    public function update(UpdateBookRequest $request, Book $book, BookService $books): BookResource
    {
        $books->update($book, $request->validated());

        return new BookResource($this->loadForResource($book));
    }

    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json(status: 204);
    }

    /**
     * @return AnonymousResourceCollection<int, BookResource>
     */
    public function archive(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $user = $request->user();
        $active = $activeLibrary->resolve($user);

        $query = Book::onlyTrashed()
            ->with(['library', 'categoryPrimary', 'categorySecondary', 'authors']);
        $this->withAvailabilityCounts($query);

        if (! $user->isSuperAdmin()) {
            if (! $active) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('library_id', $active->id);
            }
        } elseif ($request->filled('library_id')) {
            $query->where('library_id', $request->integer('library_id'));
        }

        $query->orderByDesc('deleted_at')->orderByDesc('id');

        $permissions = $auth->collectionPermissions($user, Book::class);

        return BookResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function restore(Book $book): BookResource
    {
        $book->restore();

        return new BookResource($this->loadForResource($book));
    }

    public function forceDestroy(Book $book): JsonResponse
    {
        $book->forceDelete();

        return response()->json(status: 204);
    }

    private function loadForResource(Book $book): Book
    {
        return $book
            ->load(['library', 'categoryPrimary', 'categorySecondary', 'authors'])
            ->loadCount([
                'activeCopies as copies_count' => fn ($query) => $query
                    ->whereDoesntHave('writeOffs', fn ($writeOff) => $writeOff->whereNull('cancelled_at')),
                'activeCopies as available_count' => fn ($query) => $query
                    ->where('borrowed', false)
                    ->where('rec_error', false)
                    ->whereDoesntHave('writeOffs', fn ($writeOff) => $writeOff->whereNull('cancelled_at')),
            ]);
    }

    /**
     * Dodaje brojace koji iskljucuju otpisane fizicke jedinice.
     *
     * @param  Builder<Book>  $query
     */
    private function withAvailabilityCounts(Builder $query): void
    {
        $query->withCount([
            'activeCopies as copies_count' => fn ($sub) => $sub
                ->whereDoesntHave('writeOffs', fn ($writeOff) => $writeOff->whereNull('cancelled_at')),
            'activeCopies as available_count' => fn ($sub) => $sub
                ->where('borrowed', false)
                ->where('rec_error', false)
                ->whereDoesntHave('writeOffs', fn ($writeOff) => $writeOff->whereNull('cancelled_at')),
        ]);
    }
}
