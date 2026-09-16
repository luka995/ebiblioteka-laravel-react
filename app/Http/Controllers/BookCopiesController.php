<?php

namespace App\Http\Controllers;

use App\Enums\BarcodePrintFormat;
use App\Enums\BookCopyWriteOffReason;
use App\Http\Requests\PrintBookCopiesRequest;
use App\Http\Requests\RecErrorBookCopyRequest;
use App\Http\Requests\StoreBookCopiesRequest;
use App\Http\Requests\UpdateBookCopyRequest;
use App\Http\Requests\WriteOffBookCopyRequest;
use App\Http\Resources\BookCopyResource;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Queries\BookCopyFilters;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\BarcodePdfService;
use App\Services\BookCopyService;
use App\Services\BookCopyWriteOffService;
use App\Services\InventoryNumberService;
use App\Support\BarCode;
use App\Support\BarcodeLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class BookCopiesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, BookCopyResource>
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $query = BookCopy::query()->with(['book', 'activeWriteOff']);

        $query->where('library_id', $library->id);

        $query = (new BookCopyFilters)->apply($query, $request->only([
            'book_id',
            'barcode',
            'order_number',
            'isbn',
            'search',
            'seq_number',
            'publisher',
            'publish_place',
            'publish_year',
            'issue_number',
            'dimension',
            'part',
            'udk',
            'binding',
            'origin',
            'book_number',
            'place_on_shelf',
            'price_from',
            'price_to',
            'num_of_pages_from',
            'num_of_pages_to',
            'date_add_from',
            'date_add_to',
            'notice',
            'rec_error_notice',
            'borrowed',
            'reserved',
            'rec_error',
            'status',
        ]));

        $query->orderByRaw('CAST(order_number AS BIGINT)')->orderBy('id');

        $permissions = $auth->collectionPermissions($user, BookCopy::class);

        return BookCopyResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreBookCopiesRequest $request, Book $book, BookCopyService $copies): JsonResponse
    {
        $created = $copies->createForBook($book, $request->validated());

        return BookCopyResource::collection($created->map->load('book', 'activeWriteOff'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(BookCopy $bookCopy): BookCopyResource
    {
        return new BookCopyResource($bookCopy->load('book', 'activeWriteOff', 'library'));
    }

    public function update(UpdateBookCopyRequest $request, BookCopy $bookCopy, BookCopyService $copies): BookCopyResource
    {
        $copies->update($bookCopy, $request->validated());

        return new BookCopyResource($bookCopy->load('book', 'activeWriteOff'));
    }

    public function destroy(BookCopy $bookCopy): JsonResponse
    {
        if ($bookCopy->borrowed) {
            return response()->json(['message' => __('validation.custom.book_copy_borrowed')], 422);
        }

        $bookCopy->delete();

        return response()->json(status: 204);
    }

    /**
     * @return AnonymousResourceCollection<int, BookCopyResource>
     */
    public function archive(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $query = BookCopy::onlyTrashed()->with(['book', 'activeWriteOff']);

        $query->where('library_id', $library->id);

        if ($request->filled('book_id')) {
            $query->where('book_id', $request->integer('book_id'));
        }

        $query->orderByDesc('deleted_at')->orderByDesc('id');

        $permissions = $auth->collectionPermissions($user, BookCopy::class);

        return BookCopyResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function syncInventorySequence(Request $request, ActiveLibraryService $activeLibrary, InventoryNumberService $numbers): JsonResponse
    {
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

        $last = $numbers->syncToMaxExisting($library);

        return response()->json([
            'last_number' => $last,
            'next_auto' => $last + 1,
        ]);
    }

    public function restore(BookCopy $bookCopy): BookCopyResource
    {
        // Arhivirana kopija se ne vraca u fond ako joj je broj u medjuvremenu zauzet.
        $duplicate = BookCopy::withTrashed()
            ->where('library_id', $bookCopy->library_id)
            ->whereKeyNot($bookCopy->getKey())
            ->whereRaw('CAST(order_number AS BIGINT) = ?', [(int) $bookCopy->order_number])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'order_number' => __('validation.custom.book_copy_duplicate_order_number'),
            ]);
        }

        $bookCopy->restore();

        return new BookCopyResource($bookCopy->load('book', 'activeWriteOff'));
    }

    public function forceDestroy(BookCopy $bookCopy): JsonResponse
    {
        $bookCopy->forceDelete();

        return response()->json(status: 204);
    }

    public function writeOff(WriteOffBookCopyRequest $request, BookCopy $bookCopy, BookCopyWriteOffService $writeOffs): BookCopyResource
    {
        $writeOffs->writeOff(
            $bookCopy,
            BookCopyWriteOffReason::from($request->validated('reason')),
            $request->validated('notice'),
            $request->validated('occurred_at'),
            $request->user(),
        );

        return new BookCopyResource($bookCopy->load('book', 'activeWriteOff'));
    }

    public function cancelWriteOff(Request $request, BookCopy $bookCopy, BookCopyWriteOffService $writeOffs): BookCopyResource
    {
        $writeOffs->cancel($bookCopy, $request->user());

        return new BookCopyResource($bookCopy->load('book', 'activeWriteOff'));
    }

    public function recError(RecErrorBookCopyRequest $request, BookCopy $bookCopy, BookCopyWriteOffService $writeOffs): BookCopyResource
    {
        $writeOffs->setRecError(
            $bookCopy,
            $request->boolean('rec_error'),
            $request->validated('rec_error_notice'),
        );

        return new BookCopyResource($bookCopy->load('book', 'activeWriteOff'));
    }

    public function bulkPrint(PrintBookCopiesRequest $request, BarcodePdfService $pdf, ActiveLibraryService $activeLibrary): Response|JsonResponse
    {
        $user = $request->user();
        $ids = $request->copyIds();

        $query = BookCopy::query()->with('book')->whereIn('id', $ids);

        if (! $user->isSuperAdmin()) {
            $active = $activeLibrary->resolve($user);

            if (! $active) {
                return response()->json(['message' => __('validation.custom.active_library_required')], 422);
            }

            $query->where('library_id', $active->id);
        }

        $copies = $query->get();

        if ($copies->count() !== count($ids)) {
            return response()->json(['message' => __('validation.custom.library_not_managed')], 422);
        }

        $labels = [];
        $invalid = 0;
        $libraryNames = [];

        foreach ($copies as $copy) {
            if ($copy->barcode === null || ! BarCode::validate($copy->barcode)) {
                $invalid++;

                continue;
            }

            $libraryNames[$copy->library_id] ??= Library::whereKey($copy->library_id)->value('name') ?? '';

            $labels[] = new BarcodeLabel(
                $copy->barcode,
                $copy->book?->name,
                array_filter([$libraryNames[$copy->library_id]]),
            );
        }

        if ($invalid > 0 || $labels === []) {
            return response()->json([
                'message' => __('validation.custom.book_copies_without_barcode', ['count' => $invalid]),
            ], 422);
        }

        $format = BarcodePrintFormat::from($request->validated('format'));
        $contents = $pdf->render($labels, $format);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="barkodovi-knjiga.pdf"',
        ]);
    }
}
