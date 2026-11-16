<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\BorrowResource;
use App\Http\Resources\ReservationResource;
use App\Models\Book;
use App\Models\Reservation;
use App\Services\ActiveLibraryService;
use App\Services\BorrowService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReservationsController extends Controller
{
    /**
     * Sopstvene aktivne rezervacije prijavljenog korisnika.
     *
     * @return AnonymousResourceCollection<int, ReservationResource>
     */
    public function index(Request $request, ReservationService $reservations): AnonymousResourceCollection
    {
        $reservations->expireOverdue();

        $items = Reservation::query()
            ->with(['book.authors', 'bookCopy', 'library'])
            ->where('user_id', $request->user()->id)
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', now())
            ->orderBy('valid_till')
            ->orderBy('id')
            ->get();

        return ReservationResource::collection($items);
    }

    /**
     * Sve rezervacije u aktivnoj biblioteci — uvid za osoblje.
     *
     * `status` moze biti `active` (podrazumevano), `history` ili `all`.
     *
     * @return AnonymousResourceCollection<int, ReservationResource>
     */
    public function library(
        Request $request,
        ActiveLibraryService $activeLibrary,
        ReservationService $reservations,
    ): AnonymousResourceCollection {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        $reservations->expireOverdue();

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $status = trim((string) $request->query('status', 'active'));

        $query = Reservation::query()
            ->with(['book.authors', 'bookCopy', 'library', 'user'])
            ->where('library_id', $library->id);

        if (! empty($data['date_from'])) {
            $query->whereDate('from_date', '>=', $data['date_from']);
        }

        if (! empty($data['date_to'])) {
            $query->whereDate('from_date', '<=', $data['date_to']);
        }

        if ($status === 'active') {
            $query->where('status', ReservationStatus::Active)
                ->where('valid_till', '>', now())
                ->orderBy('valid_till')
                ->orderBy('id');
        } elseif ($status === 'history') {
            $query->where('status', '!=', ReservationStatus::Active)
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->string('search'));

            $query->where(function ($group) use ($term): void {
                $group->whereHas('book', fn ($book) => $book->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('user', function ($user) use ($term): void {
                        $user->where('first_name', 'like', '%'.$term.'%')
                            ->orWhere('last_name', 'like', '%'.$term.'%')
                            ->orWhere('name', 'like', '%'.$term.'%')
                            ->orWhere('bar_code', 'like', '%'.$term.'%');
                    })
                    ->orWhereHas('bookCopy', fn ($copy) => $copy->where('barcode', 'like', '%'.$term.'%'));
            });
        }

        return ReservationResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        );
    }

    public function store(
        StoreReservationRequest $request,
        ReservationService $reservations,
    ): Response {
        $book = Book::query()
            ->with('authors')
            ->findOrFail($request->integer('book_id'));

        $reservations->create($request->user(), $book);

        return response()->noContent(201);
    }

    public function destroy(Request $request, Reservation $reservation, ReservationService $reservations): Response
    {
        $reservations->cancel($reservation, $request->user());

        return response()->noContent();
    }

    /**
     * Realizuje rezervaciju i izdaje rezervisani primerak.
     */
    public function issue(
        Request $request,
        Reservation $reservation,
        BorrowService $borrows,
    ): JsonResponse {
        $data = $request->validate([
            'valid_till' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $borrow = $borrows->issueFromReservation(
            $request->user(),
            $reservation,
            $data['valid_till'] ?? null,
        );

        return (new BorrowResource($borrow))->response()->setStatusCode(201);
    }
}
