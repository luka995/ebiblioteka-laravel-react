<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Http\Resources\CatalogBookResource;
use App\Http\Resources\ReservationResource;
use App\Models\Book;
use App\Models\LibrarySettings;
use App\Models\Reservation;
use App\Services\BookCopyAvailabilityService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Detalj naslova za prijavljenog korisnika (clana), sa stanjem
     * rezervacije i dostupnoscu.
     */
    public function book(
        Request $request,
        Book $book,
        BookCopyAvailabilityService $availability,
        ReservationService $reservations,
    ): JsonResponse {
        $book->loadMissing(['library.settings', 'authors', 'categoryPrimary']);

        abort_if($book->trashed() || $book->library === null || $book->library->deleted, 404);

        $reservations->expireOverdue();

        $user = $request->user();
        $library = $book->library;
        $settings = $library->settings()->firstOrCreate([], [
            'reservation_hours' => LibrarySettings::DEFAULT_RESERVATION_HOURS,
        ]);

        $isMember = $user->libraries()->whereKey($library->id)->exists();

        $activeReservation = Reservation::query()
            ->with(['bookCopy', 'library'])
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->where('library_id', $library->id)
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', now())
            ->first();

        $availabilityData = $availability->forBook($book);

        return response()->json([
            'data' => (new CatalogBookResource($book))->resolve($request),
            'availability' => $availabilityData,
            'publish_year' => $book->activeCopies()->min('publish_year'),
            'reservation_hours' => $settings->reservationHours(),
            'membership' => $isMember,
            'active_reservation' => $activeReservation === null
                ? null
                : (new ReservationResource($activeReservation->loadMissing('book.authors')))->resolve($request),
            'can_reserve' => $isMember
                && $activeReservation === null
                && $availabilityData['available'] > 0,
        ]);
    }
}
