<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationExchangeRequiredException;
use App\Http\Requests\StoreBorrowRequest;
use App\Http\Resources\BorrowResource;
use App\Models\BookCopy;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\LibrarySettings;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\BorrowFilters;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\BorrowService;
use App\Support\BarCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BorrowsController extends Controller
{
    /**
     * Sopstvene pozajmice prijavljenog korisnika.
     *
     * @return AnonymousResourceCollection<int, BorrowResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Borrow::query()
            ->with($this->relations())
            ->where('user_id', $request->user()->id);

        $filters = $request->only(['status', 'search', 'date_from', 'date_to']);
        $filters['status'] ??= 'active';

        $query = (new BorrowFilters)->apply($query, $filters);

        $this->applyOrdering($query, (string) $filters['status']);

        return BorrowResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        );
    }

    /**
     * Pozajmice konkretnog korisnika za tab na profilu.
     *
     * @return AnonymousResourceCollection<int, BorrowResource>
     */
    public function forUser(Request $request, User $user): AnonymousResourceCollection
    {
        $query = Borrow::query()
            ->with($this->relations())
            ->where('user_id', $user->id);

        $filters = $request->only(['status', 'search', 'date_from', 'date_to']);
        $filters['status'] ??= 'active';

        $query = (new BorrowFilters)->apply($query, $filters);

        $this->applyOrdering($query, (string) $filters['status']);

        return BorrowResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        );
    }

    /**
     * Indeks pozajmica aktivne biblioteke — dostupno osoblju.
     *
     * @return AnonymousResourceCollection<int, BorrowResource>
     */
    public function library(
        Request $request,
        ActiveLibraryService $activeLibrary,
        AuthorizationService $auth,
    ): AnonymousResourceCollection {
        $actor = $request->user();
        $library = $activeLibrary->requireActiveLibrary($actor);
        $this->ensureLibraryStaff($actor, $library);

        $query = Borrow::query()
            ->with($this->relations())
            ->where('library_id', $library->id);

        $filters = $request->only(['status', 'search', 'date_from', 'date_to', 'overdue']);
        $filters['status'] ??= 'active';

        $query = (new BorrowFilters)->apply($query, $filters);

        $this->applyOrdering($query, (string) $filters['status']);

        $permissions = $auth->collectionPermissions($actor, Borrow::class);

        return BorrowResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    /**
     * Razresavanje korisnika (barkod/username) i kopije (barkod) pre izdavanja.
     */
    public function lookup(Request $request, ActiveLibraryService $activeLibrary): JsonResponse
    {
        $actor = $request->user();
        $library = $activeLibrary->requireActiveLibrary($actor);
        $this->ensureLibraryStaff($actor, $library);

        $data = $request->validate([
            'user' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:32'],
        ]);

        $userTerm = trim((string) ($data['user'] ?? ''));
        $barcode = trim((string) ($data['barcode'] ?? ''));

        if ($userTerm === '' && $barcode === '') {
            throw ValidationException::withMessages([
                'user' => __('validation.custom.borrow_lookup_term_required'),
            ]);
        }

        $settings = $this->settings($library);

        return response()->json([
            'data' => [
                'user' => $userTerm !== '' ? $this->lookupUser($userTerm, $library) : null,
                'users' => $userTerm !== '' ? $this->lookupUserSuggestions($userTerm, $library) : [],
                'copy' => $barcode !== '' ? $this->lookupCopy($barcode, $library) : null,
                'defaults' => [
                    'lend_days' => $settings->lendDays(),
                    'overdue_price_per_day' => $settings->overduePricePerDay(),
                    'valid_till' => now()->addDays($settings->lendDays())->endOfDay()->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Izdavanje fizicke jedinice po barkodu/kopiji i korisniku.
     */
    public function store(
        StoreBorrowRequest $request,
        ActiveLibraryService $activeLibrary,
        BorrowService $borrows,
    ): JsonResponse {
        $actor = $request->user();
        $library = $activeLibrary->requireActiveLibrary($actor);
        $this->ensureLibraryStaff($actor, $library);

        $user = $this->resolveUser($request);
        $copy = $this->resolveCopy($request, $library);

        try {
            $borrow = $borrows->issue(
                $actor,
                $library,
                $user,
                $copy,
                $request->input('valid_till'),
                $request->boolean('confirm_reservation_exchange'),
            );
        } catch (ReservationExchangeRequiredException $exception) {
            return $this->reservationConflictResponse($exception->reservation, $copy);
        }

        return (new BorrowResource($borrow))->response()->setStatusCode(201);
    }

    /**
     * Razduzivanje pozajmice.
     */
    public function returnBook(Request $request, Borrow $borrow, BorrowService $borrows): BorrowResource
    {
        return new BorrowResource($borrows->returnBook($request->user(), $borrow));
    }

    private function resolveUser(StoreBorrowRequest $request): User
    {
        if ($request->filled('user_id')) {
            return User::query()->findOrFail($request->integer('user_id'));
        }

        if ($request->filled('user_barcode')) {
            $user = User::query()
                ->where('bar_code', (string) $request->input('user_barcode'))
                ->first();

            if ($user === null) {
                throw ValidationException::withMessages([
                    'user_barcode' => __('validation.custom.borrow_user_not_found'),
                ]);
            }

            return $user;
        }

        $username = (string) $request->input('username');

        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'username' => __('validation.custom.borrow_user_not_found'),
            ]);
        }

        return $user;
    }

    private function resolveCopy(StoreBorrowRequest $request, Library $library): BookCopy
    {
        if ($request->filled('book_copy_id')) {
            $copy = BookCopy::withTrashed()->find($request->integer('book_copy_id'));

            if ($copy === null || $copy->trashed()) {
                throw ValidationException::withMessages([
                    'book_copy_id' => __('validation.custom.borrow_copy_not_found'),
                ]);
            }

            return $copy;
        }

        $copy = BookCopy::query()
            ->where('library_id', $library->id)
            ->where('barcode', (string) $request->input('barcode'))
            ->first();

        if ($copy === null) {
            throw ValidationException::withMessages([
                'barcode' => __('validation.custom.borrow_copy_not_found'),
            ]);
        }

        return $copy;
    }

    private function reservationConflictResponse(Reservation $reservation, BookCopy $copy): JsonResponse
    {
        $reservation->loadMissing(['book', 'bookCopy', 'user']);

        return response()->json([
            'message' => __('validation.custom.borrow_reservation_exchange_required'),
            'reservation_conflict' => [
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'user_name' => $reservation->user?->displayName(),
                'book_id' => $reservation->book_id,
                'book_name' => $reservation->book?->name,
                'reserved_book_copy_id' => $reservation->book_copy_id,
                'reserved_barcode' => $reservation->bookCopy?->barcode,
                'reserved_order_number' => $reservation->bookCopy?->order_number,
                'reserved_valid_till' => $reservation->valid_till?->toIso8601String(),
                'reserved_valid_till_formatted' => $reservation->valid_till?->format('d.m.Y. H:i'),
                'scanned_book_copy_id' => $copy->id,
                'scanned_barcode' => $copy->barcode,
                'scanned_order_number' => $copy->order_number,
            ],
        ], 409);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookupUser(string $term, Library $library): ?array
    {
        $query = User::query();

        if (preg_match('/^\d+$/', $term) === 1) {
            $query->where('bar_code', BarCode::normalizeScanInput($term));
        } else {
            $query->whereRaw('LOWER(username) = ?', [mb_strtolower($term)]);
        }

        $user = $query->first();

        return $user === null ? null : $this->userSummary($user, $library);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lookupUserSuggestions(string $term, Library $library): array
    {
        $normalized = preg_match('/^\d+$/', $term) === 1 ? BarCode::normalizeScanInput($term) : $term;

        return User::query()
            ->whereHas('libraries', fn ($query) => $query->whereKey($library->id))
            ->where(function ($query) use ($term, $normalized): void {
                $query->where('first_name', 'like', '%'.$term.'%')
                    ->orWhere('last_name', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('username', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('bar_code', 'like', '%'.$normalized.'%');
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(10)
            ->get()
            ->map(fn (User $user): array => $this->userSummary($user, $library))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function userSummary(User $user, Library $library): array
    {
        return [
            'id' => $user->id,
            'name' => $user->displayName(),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
            'bar_code' => $user->bar_code,
            'is_member' => $user->isMemberOf($library->id),
            'active_borrows' => $user->borrows()->where('library_id', $library->id)->where('returned', false)->count(),
            'active_reservations' => $user->reservations()->where('library_id', $library->id)->where('status', 'active')->where('valid_till', '>', now())->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookupCopy(string $barcode, Library $library): ?array
    {
        $copy = BookCopy::query()
            ->with(['book.authors', 'library.settings', 'activeReservation.user', 'activeBorrow.user'])
            ->where('library_id', $library->id)
            ->where('barcode', BarCode::normalizeScanInput($barcode))
            ->first();

        if ($copy === null) {
            return null;
        }

        $reservation = $copy->activeReservation;
        $borrow = $copy->activeBorrow;
        $writtenOff = $copy->isWrittenOff();

        $blockReason = match (true) {
            (bool) $copy->borrowed => $borrow?->user
                ? __('validation.custom.borrow_copy_borrowed_by', [
                    'name' => $borrow->user->displayName(),
                    'date' => $borrow->valid_till?->format('d.m.Y. H:i') ?? '—',
                ])
                : __('validation.custom.borrow_copy_already_borrowed'),
            (bool) $copy->rec_error => __('validation.custom.borrow_copy_rec_error'),
            $writtenOff => __('validation.custom.borrow_copy_written_off'),
            default => null,
        };

        return [
            'id' => $copy->id,
            'book_id' => $copy->book_id,
            'book_name' => $copy->book?->name,
            'book_authors' => $copy->book?->authors
                ?->map(fn ($author): string => $author->displayName())
                ->join('; '),
            'barcode' => $copy->barcode,
            'order_number' => $copy->order_number,
            'status' => match (true) {
                $writtenOff => 'written_off',
                (bool) $copy->rec_error => 'record_error',
                (bool) $copy->borrowed => 'borrowed',
                (bool) $copy->reserved => 'reserved',
                default => 'available',
            },
            'issuable' => $blockReason === null,
            'block_reason' => $blockReason,
            'active_reservation' => $reservation === null ? null : [
                'id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'user_name' => $reservation->user?->displayName(),
                'valid_till' => $reservation->valid_till?->toIso8601String(),
                'valid_till_formatted' => $reservation->valid_till?->format('d.m.Y. H:i'),
            ],
            'active_borrow' => $borrow === null ? null : [
                'id' => $borrow->id,
                'user_id' => $borrow->user_id,
                'user_name' => $borrow->user?->displayName(),
                'valid_till_formatted' => $borrow->valid_till?->format('d.m.Y. H:i'),
            ],
        ];
    }

    /**
     * @param  Builder<Borrow>  $query
     */
    private function applyOrdering($query, string $status): void
    {
        if ($status === 'history') {
            $query->orderByDesc('return_date')->orderByDesc('id');

            return;
        }

        if ($status === 'active' || $status === 'overdue') {
            $query->orderBy('valid_till')->orderBy('id');

            return;
        }

        $query->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'library.settings',
            'user',
            'book.authors',
            'bookCopy',
            'createdBy',
        ];
    }

    private function settings(Library $library): LibrarySettings
    {
        return $library->settings()->firstOrCreate([], [
            'reservation_hours' => LibrarySettings::DEFAULT_RESERVATION_HOURS,
            'lend_days' => LibrarySettings::DEFAULT_LEND_DAYS,
            'overdue_price_per_day' => LibrarySettings::DEFAULT_OVERDUE_PRICE_PER_DAY,
        ]);
    }

    private function ensureLibraryStaff(User $actor, Library $library): void
    {
        abort_unless($actor->isSuperAdmin() || $actor->isStaffIn($library->id), 403);
    }
}
