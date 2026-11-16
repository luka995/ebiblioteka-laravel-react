<?php

use App\Enums\LibraryRole;
use App\Enums\ReservationStatus;
use App\Models\Book;
use App\Models\BookHistory;
use App\Models\BookCopy;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BarCode;

function borrowLibrary(): Library
{
    return Library::factory()->create();
}

function borrowMember(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id, ['role' => LibraryRole::User->value]);

    return $user;
}

function borrowStaff(Library $library, LibraryRole $role = LibraryRole::Librarian): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id, ['role' => $role->value]);

    return $user;
}

function borrowBook(Library $library): Book
{
    return Book::factory()->for($library)->create();
}

function borrowCopy(Book $book, array $attributes = []): BookCopy
{
    return BookCopy::factory()
        ->for($book->library)
        ->for($book)
        ->create($attributes);
}

test('staff issues an available copy by barcode without a reservation', function () {
    $library = borrowLibrary();
    $library->settings()->update(['lend_days' => 21]);
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_id' => $member->id,
            'barcode' => $copy->barcode,
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $member->id)
        ->assertJsonPath('data.book_copy_id', $copy->id)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.reservation_id', null);

    $borrow = Borrow::query()->firstOrFail();

    expect($borrow->returned)->toBeFalse()
        ->and($borrow->reservation_id)->toBeNull()
        ->and($copy->fresh()->borrowed)->toBeTrue()
        ->and($borrow->valid_till->isSameDay(now()->addDays(21)))->toBeTrue()
        ->and(BookHistory::query()->where('action', BookHistory::ACTION_BORROW)->exists())->toBeTrue();
});

test('staff can issue by scanning the user barcode', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $member->update(['bar_code' => BarCode::generate()]);
    $book = borrowBook($library);
    $copy = borrowCopy($book);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_barcode' => $member->bar_code,
            'book_copy_id' => $copy->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $member->id);

    expect($copy->fresh()->borrowed)->toBeTrue();
});

test('a borrowed, written off or record-error copy cannot be issued', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);

    $borrowed = borrowCopy($book, ['borrowed' => true]);
    $recError = borrowCopy($book, ['rec_error' => true]);
    $writtenOff = borrowCopy($book);
    app(\App\Services\BookCopyWriteOffService::class)->writeOff(
        $writtenOff,
        \App\Enums\BookCopyWriteOffReason::Unusable,
        null,
        null,
        $staff,
    );

    foreach ([$borrowed, $recError, $writtenOff] as $copy) {
        $this->actingAs($staff)
            ->withSession(['active_library_id' => $library->id])
            ->postJson('/api/v1/borrows', [
                'user_id' => $member->id,
                'book_copy_id' => $copy->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    expect(Borrow::count())->toBe(0);
});

test('a user who is not a member of the active library cannot borrow', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $outsider = User::factory()->create();
    $book = borrowBook($library);
    $copy = borrowCopy($book);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_id' => $outsider->id,
            'book_copy_id' => $copy->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');

    expect(Borrow::count())->toBe(0);
});

test('a regular user cannot issue loans or list library loans', function () {
    $library = borrowLibrary();
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book);

    $this->actingAs($member)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/borrows/library')
        ->assertForbidden();
});

test('a copy reserved for another user cannot be issued', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $owner = borrowMember($library);
    $other = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book, ['reserved' => true]);

    Reservation::factory()->forCopy($copy, $owner)->create();

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_id' => $other->id,
            'book_copy_id' => $copy->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('barcode');

    expect(Borrow::count())->toBe(0)
        ->and($copy->fresh()->borrowed)->toBeFalse()
        ->and($copy->fresh()->reserved)->toBeTrue();
});

test('issuing the reserved copy fulfils the reservation', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book, ['reserved' => true]);

    $reservation = Reservation::factory()->forCopy($copy, $member)->create();

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.reservation_id', $reservation->id);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Fulfilled)
        ->and($copy->fresh()->borrowed)->toBeTrue()
        ->and($copy->fresh()->reserved)->toBeFalse()
        ->and(BookHistory::query()->where('action', BookHistory::ACTION_RESERVATION_FULFILLED)->exists())->toBeTrue();
});

test('issuing a different copy requires confirmation when the user has a reservation for another copy', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $reservedCopy = borrowCopy($book, ['reserved' => true]);
    $scannedCopy = borrowCopy($book);

    $reservation = Reservation::factory()->forCopy($reservedCopy, $member)->create();

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_id' => $member->id,
            'book_copy_id' => $scannedCopy->id,
        ])
        ->assertStatus(409)
        ->assertJsonPath('reservation_conflict.reservation_id', $reservation->id)
        ->assertJsonPath('reservation_conflict.reserved_barcode', $reservedCopy->barcode)
        ->assertJsonPath('reservation_conflict.scanned_barcode', $scannedCopy->barcode);

    expect(Borrow::count())->toBe(0)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Active)
        ->and($reservedCopy->fresh()->reserved)->toBeTrue()
        ->and($scannedCopy->fresh()->borrowed)->toBeFalse();

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson('/api/v1/borrows', [
            'user_id' => $member->id,
            'book_copy_id' => $scannedCopy->id,
            'confirm_reservation_exchange' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.reservation_id', $reservation->id);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Fulfilled)
        ->and($reservedCopy->fresh()->reserved)->toBeFalse()
        ->and($reservedCopy->fresh()->borrowed)->toBeFalse()
        ->and($scannedCopy->fresh()->borrowed)->toBeTrue();
});

test('a reservation can be issued directly from the reservations page', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book, ['reserved' => true]);

    $reservation = Reservation::factory()->forCopy($copy, $member)->create();

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/reservations/{$reservation->id}/issue")
        ->assertCreated()
        ->assertJsonPath('data.reservation_id', $reservation->id);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Fulfilled)
        ->and($copy->fresh()->borrowed)->toBeTrue()
        ->and($copy->fresh()->reserved)->toBeFalse();
});

test('returning a loan releases the copy and calculates overdue amount', function () {
    $library = borrowLibrary();
    $library->settings()->update(['overdue_price_per_day' => 15]);
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book, ['borrowed' => true]);

    $borrow = Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'created_by_id' => $staff->id,
        'from_date' => now()->subDays(10),
        'valid_till' => now()->subDays(3),
        'returned' => false,
    ]);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/borrows/{$borrow->id}/return")
        ->assertOk()
        ->assertJsonPath('data.returned', true)
        ->assertJsonPath('data.overdue_days', 3)
        ->assertJsonPath('data.overdue_amount', 45);

    expect($borrow->fresh()->returned)->toBeTrue()
        ->and($copy->fresh()->borrowed)->toBeFalse()
        ->and(BookHistory::query()->where('action', BookHistory::ACTION_RETURN)->exists())->toBeTrue();
});

test('a loan cannot be returned twice', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book);

    $borrow = Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'from_date' => now()->subDay(),
        'valid_till' => now()->addDays(20),
        'returned' => false,
    ]);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/borrows/{$borrow->id}/return")
        ->assertOk();

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/borrows/{$borrow->id}/return")
        ->assertForbidden();
});

test('library loans are scoped to the active library and can be filtered', function () {
    $library = borrowLibrary();
    $otherLibrary = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book);

    $active = Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'from_date' => now(),
        'valid_till' => now()->addDays(10),
        'returned' => false,
    ]);

    $otherBook = borrowBook($otherLibrary);
    $otherCopy = borrowCopy($otherBook);
    $otherMember = borrowMember($otherLibrary);
    Borrow::create([
        'library_id' => $otherLibrary->id,
        'user_id' => $otherMember->id,
        'book_id' => $otherBook->id,
        'book_copy_id' => $otherCopy->id,
        'from_date' => now(),
        'valid_till' => now()->addDays(10),
        'returned' => false,
    ]);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/borrows/library?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/borrows/library?status=history')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('my borrows endpoint returns only the authenticated user borrows', function () {
    $library = borrowLibrary();
    $member = borrowMember($library);
    $other = borrowMember($library);
    $book = borrowBook($library);

    $mineCopy = borrowCopy($book);
    $otherCopy = borrowCopy($book);

    $mine = Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $mineCopy->id,
        'from_date' => now(),
        'valid_till' => now()->addDays(10),
        'returned' => false,
    ]);

    Borrow::create([
        'library_id' => $library->id,
        'user_id' => $other->id,
        'book_id' => $book->id,
        'book_copy_id' => $otherCopy->id,
        'from_date' => now(),
        'valid_till' => now()->addDays(10),
        'returned' => false,
    ]);

    $this->actingAs($member)
        ->getJson('/api/v1/borrows')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

test('lookup resolves a user and a copy with reservation details', function () {
    $library = borrowLibrary();
    $staff = borrowStaff($library);
    $member = borrowMember($library);
    $book = borrowBook($library);
    $copy = borrowCopy($book, ['reserved' => true]);
    Reservation::factory()->forCopy($copy, $member)->create();

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/borrows/lookup?barcode='.$copy->barcode.'&user='.$member->username)
        ->assertOk()
        ->assertJsonPath('data.copy.barcode', $copy->barcode)
        ->assertJsonPath('data.copy.active_reservation.user_id', $member->id)
        ->assertJsonPath('data.defaults.lend_days', 30);
});

test('library lending settings are exposed and can be updated', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = borrowLibrary();

    $this->actingAs($admin)
        ->getJson("/api/v1/libraries/{$library->id}/settings")
        ->assertOk()
        ->assertJsonPath('data.lend_days', 30)
        ->assertJsonPath('data.overdue_price_per_day', 0);

    $this->actingAs($admin)
        ->putJson("/api/v1/libraries/{$library->id}/settings", [
            'reservation_hours' => 48,
            'lend_days' => 14,
            'overdue_price_per_day' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('data.lend_days', 14)
        ->assertJsonPath('data.overdue_price_per_day', 10);
});
