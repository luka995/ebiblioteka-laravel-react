<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\ReservationListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\BorrowResource;
use App\Http\Resources\ReservationResource;
use App\Models\Book;
use App\Models\Reservation;
use App\Services\ActiveLibraryService;
use App\Services\BorrowService;
use App\Services\Queries\ReservationQueryService;
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
    public function index(
        Request $request,
        ReservationService $reservations,
        ReservationQueryService $queryService,
    ): AnonymousResourceCollection {
        $reservations->expireOverdue();

        $dto = new ReservationListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            perPage: $request->integer('per_page', 25),
        );

        return ReservationResource::collection(
            $queryService->forUser($dto, $request->user()->id)
                ->paginate($dto->perPage)
                ->withQueryString()
        );
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
        ReservationQueryService $queryService,
    ): AnonymousResourceCollection {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        $reservations->expireOverdue();

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dto = new ReservationListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
            $data['date_from'] ?? null,
            $data['date_to'] ?? null,
            $request->integer('per_page', 25),
        );

        return ReservationResource::collection(
            $queryService->forLibrary($dto, $library)
                ->paginate($dto->perPage)
                ->withQueryString()
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
