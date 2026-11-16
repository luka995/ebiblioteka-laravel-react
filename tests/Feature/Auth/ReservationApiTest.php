<?php

use App\Enums\BookCopyWriteOffReason;
use App\Enums\ReservationStatus;
use App\Enums\LibraryRole;
use App\Models\Book;
use App\Models\BookHistory;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BookCopyAvailabilityService;
use App\Services\BookCopyWriteOffService;
use Illuminate\Validation\ValidationException;

function reservationMember(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id);

    return $user;
}

function reservationBook(Library $library): Book
{
    return Book::factory()->for($library)->create();
}

function reservationCopy(Book $book, array $attributes = []): BookCopy
{
    return BookCopy::factory()
        ->for($book->library)
        ->for($book)
        ->create($attributes);
}

test('a member reserves the first available copy', function () {
    $library = Library::factory()->create();
    $library->settings()->update(['reservation_hours' => 24]);
    $user = reservationMember($library);
    $book = reservationBook($library);
    $copy = reservationCopy($book);

    $this->actingAs($user)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertCreated();

    $reservation = Reservation::query()->firstOrFail();

    expect($reservation->status)->toBe(ReservationStatus::Active)
        ->and($reservation->user_id)->toBe($user->id)
        ->and($reservation->book_copy_id)->toBe($copy->id)
        ->and($copy->fresh()->reserved)->toBeTrue()
        ->and($reservation->valid_till->diffInHours(now()))->toBeLessThanOrEqual(24)
        ->and($reservation->valid_till->isFuture())->toBeTrue();

    expect(BookHistory::query()->where('book_copy_id', $copy->id)->where('action', BookHistory::ACTION_RESERVATION)->exists())->toBeTrue();

    $this->actingAs($user)
        ->getJson('/api/v1/reservations')
        ->assertOk()
        ->assertJsonPath('data.0.book_name', $book->name)
        ->assertJsonPath('data.0.status', 'active');
});

test('a user without membership in the library cannot reserve', function () {
    $library = Library::factory()->create();
    $user = User::factory()->create();
    $book = reservationBook($library);
    reservationCopy($book);

    $this->actingAs($user)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('book_id');

    expect(Reservation::count())->toBe(0);
});

test('a user cannot have two active reservations for the same title', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);
    $book = reservationBook($library);
    reservationCopy($book);
    reservationCopy($book);

    $this->actingAs($user)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('book_id');

    expect(Reservation::count())->toBe(1);
});

test('reservation is rejected when there is no available copy', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);
    $book = reservationBook($library);
    reservationCopy($book, ['borrowed' => true]);

    $this->actingAs($user)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('book_id');
});

test('a reservation can be cancelled by its owner and releases the copy', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);
    $book = reservationBook($library);
    $copy = reservationCopy($book);

    $this->actingAs($user)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertCreated();

    $reservation = Reservation::query()->firstOrFail();

    $this->actingAs($user)
        ->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertNoContent();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($copy->fresh()->reserved)->toBeFalse();
});

test('another user cannot cancel a reservation they do not own', function () {
    $library = Library::factory()->create();
    $owner = reservationMember($library);
    $other = reservationMember($library);
    $book = reservationBook($library);
    reservationCopy($book);

    $this->actingAs($owner)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertCreated();

    $reservation = Reservation::query()->firstOrFail();

    $this->actingAs($other)
        ->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertForbidden();
});

test('library staff can cancel a reservation in their library', function () {
    $library = Library::factory()->create();
    $owner = reservationMember($library);
    $staff = User::factory()->create();
    $staff->libraries()->attach($library->id, ['role' => LibraryRole::Librarian->value]);
    $book = reservationBook($library);
    $copy = reservationCopy($book);

    $this->actingAs($owner)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertCreated();

    $reservation = Reservation::query()->firstOrFail();

    $this->actingAs($staff)
        ->deleteJson("/api/v1/reservations/{$reservation->id}")
        ->assertNoContent();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($copy->fresh()->reserved)->toBeFalse();
});

test('the expire command marks overdue reservations and releases copies', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);
    $book = reservationBook($library);
    $copy = reservationCopy($book, ['reserved' => true]);

    $expired = Reservation::factory()
        ->forCopy($copy, $user)
        ->create(['valid_till' => now()->subMinute()]);

    $otherUser = reservationMember($library);
    $activeCopy = reservationCopy($book, ['reserved' => true]);
    $active = Reservation::factory()
        ->forCopy($activeCopy, $otherUser)
        ->create(['valid_till' => now()->addHour()]);

    $this->artisan('reservations:expire')
        ->expectsOutputToContain('Obradjeno isteklih rezervacija: 1')
        ->assertSuccessful();

    expect($expired->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($copy->fresh()->reserved)->toBeFalse()
        ->and(BookHistory::query()->where('book_copy_id', $copy->id)->where('action', BookHistory::ACTION_RESERVATION_EXPIRED)->exists())->toBeTrue()
        ->and($active->fresh()->status)->toBe(ReservationStatus::Active)
        ->and($activeCopy->fresh()->reserved)->toBeTrue();
});

test('the expire command is idempotent', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);
    $book = reservationBook($library);
    $copy = reservationCopy($book, ['reserved' => true]);

    Reservation::factory()
        ->forCopy($copy, $user)
        ->create(['valid_till' => now()->subMinute()]);

    $this->artisan('reservations:expire')->assertSuccessful();
    $this->artisan('reservations:expire')
        ->expectsOutputToContain('Obradjeno isteklih rezervacija: 0')
        ->assertSuccessful();

    expect(Reservation::query()->where('status', ReservationStatus::Expired)->count())->toBe(1);
});

test('availability excludes reserved copies', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);
    $book = reservationBook($library);
    reservationCopy($book);
    reservationCopy($book, ['reserved' => true]);
    reservationCopy($book, ['borrowed' => true]);

    $availability = app(BookCopyAvailabilityService::class)->forBook($book);

    expect($availability['total'])->toBe(3)
        ->and($availability['available'])->toBe(1);

    $this->actingAs($user)
        ->getJson("/api/v1/catalog/books/{$book->id}")
        ->assertOk()
        ->assertJsonPath('membership', true)
        ->assertJsonPath('can_reserve', true)
        ->assertJsonPath('availability.available', 1);
});

test('catalog reflects an active reservation for the viewer', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);
    $book = reservationBook($library);
    reservationCopy($book);

    $this->actingAs($user)
        ->postJson('/api/v1/reservations', ['book_id' => $book->id])
        ->assertCreated();

    $this->actingAs($user)
        ->getJson("/api/v1/catalog/books/{$book->id}")
        ->assertOk()
        ->assertJsonPath('can_reserve', false)
        ->assertJsonPath('active_reservation.book_id', $book->id)
        ->assertJsonPath('active_reservation.can.cancel', true);
});

test('user detail includes active reservations for staff', function () {
    $library = Library::factory()->create();
    $member = reservationMember($library);
    $admin = User::factory()->create();
    $admin->libraries()->attach($library->id, ['role' => LibraryRole::LibraryAdmin->value]);
    $book = reservationBook($library);
    $copy = reservationCopy($book, ['reserved' => true]);

    Reservation::factory()
        ->forCopy($copy, $member)
        ->create(['valid_till' => now()->addHours(12)]);

    $this->actingAs($admin)
        ->getJson("/api/v1/users/{$member->id}")
        ->assertOk()
        ->assertJsonPath('data.reservations.0.book_name', $book->name)
        ->assertJsonPath('data.reservations.0.status', 'active');
});

test('a reserved copy cannot be archived or written off', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $book = reservationBook($library);
    $copy = reservationCopy($book, ['reserved' => true]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/book-copies/{$copy->id}")
        ->assertStatus(422);

    expect(fn () => app(BookCopyWriteOffService::class)->writeOff(
        $copy,
        BookCopyWriteOffReason::Unusable,
        null,
        null,
        $admin,
    ))->toThrow(ValidationException::class);

    expect($copy->fresh()->deleted_at)->toBeNull();
});

test('library settings can be read and updated by a superadmin', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();

    $this->actingAs($admin)
        ->getJson("/api/v1/libraries/{$library->id}/settings")
        ->assertOk()
        ->assertJsonPath('data.reservation_hours', 48);

    $this->actingAs($admin)
        ->putJson("/api/v1/libraries/{$library->id}/settings", ['reservation_hours' => 72])
        ->assertOk()
        ->assertJsonPath('data.reservation_hours', 72);

    expect($library->settings()->first()->reservation_hours)->toBe(72);
});

test('a library admin can update settings for their own library only', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $admin = User::factory()->create();
    $admin->libraries()->attach($library->id, ['role' => LibraryRole::LibraryAdmin->value]);

    $this->actingAs($admin)
        ->putJson("/api/v1/libraries/{$library->id}/settings", ['reservation_hours' => 24])
        ->assertOk()
        ->assertJsonPath('data.reservation_hours', 24);

    $this->actingAs($admin)
        ->putJson("/api/v1/libraries/{$otherLibrary->id}/settings", ['reservation_hours' => 12])
        ->assertForbidden();
});

test('library staff can list all reservations in the active library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $member = reservationMember($library);
    $otherMember = reservationMember($otherLibrary);
    $librarian = User::factory()->create();
    $librarian->libraries()->attach($library->id, ['role' => LibraryRole::Librarian->value]);

    $book = reservationBook($library);
    $copy = reservationCopy($book, ['reserved' => true]);
    $reservation = Reservation::factory()
        ->forCopy($copy, $member)
        ->create(['valid_till' => now()->addHours(10)]);

    $otherBook = reservationBook($otherLibrary);
    $otherCopy = reservationCopy($otherBook, ['reserved' => true]);
    Reservation::factory()
        ->forCopy($otherCopy, $otherMember)
        ->create(['valid_till' => now()->addHours(10)]);

    $this->withSession(['active_library_id' => $library->id])
        ->actingAs($librarian)
        ->getJson('/api/v1/reservations/library')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reservation->id)
        ->assertJsonPath('data.0.user_name', $member->displayName())
        ->assertJsonPath('data.0.book_name', $book->name);

    $this->withSession(['active_library_id' => $library->id])
        ->actingAs($librarian)
        ->getJson('/api/v1/reservations/library?status=history')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a regular user cannot list library reservations', function () {
    $library = Library::factory()->create();
    $user = reservationMember($library);

    $this->withSession(['active_library_id' => $library->id])
        ->actingAs($user)
        ->getJson('/api/v1/reservations/library')
        ->assertForbidden();
});

test('library reservations can be filtered by a date range', function () {
    $library = Library::factory()->create();
    $librarian = User::factory()->create();
    $librarian->libraries()->attach($library->id, ['role' => LibraryRole::Librarian->value]);

    $firstBook = reservationBook($library);
    $firstCopy = reservationCopy($firstBook, ['reserved' => true]);
    $first = Reservation::factory()
        ->forCopy($firstCopy, reservationMember($library))
        ->create(['from_date' => '2026-11-01 10:00:00', 'valid_till' => '2026-11-03 10:00:00']);

    $secondBook = reservationBook($library);
    $secondCopy = reservationCopy($secondBook, ['reserved' => true]);
    Reservation::factory()
        ->forCopy($secondCopy, reservationMember($library))
        ->create(['from_date' => '2026-11-10 10:00:00', 'valid_till' => '2026-11-12 10:00:00']);

    $this->withSession(['active_library_id' => $library->id])
        ->actingAs($librarian)
        ->getJson('/api/v1/reservations/library?status=all&date_from=2026-11-01&date_to=2026-11-02')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->id);
});
