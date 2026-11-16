<?php

use App\Enums\LibraryRole;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookHistory;
use App\Models\Library;
use App\Models\User;
use App\Services\ReservationService;

function auditLibrary(): Library
{
    return Library::factory()->create();
}

function auditMember(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id, ['role' => LibraryRole::User->value]);

    return $user;
}

function auditStaff(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id, ['role' => LibraryRole::Librarian->value]);

    return $user;
}

test('staff can see reservation audit history on a copy', function () {
    $library = auditLibrary();
    $member = auditMember($library);
    $staff = auditStaff($library);
    $book = Book::factory()->for($library)->create();
    $copy = BookCopy::factory()->for($library)->for($book)->create();

    $reservation = app(ReservationService::class)->create($member, $book);
    app(ReservationService::class)->cancel($reservation, $staff);

    $this->actingAs($staff)
        ->getJson("/api/v1/book-copies/{$copy->id}/history")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['action' => BookHistory::ACTION_RESERVATION_CANCEL])
        ->assertJsonFragment(['action' => BookHistory::ACTION_RESERVATION]);
});

test('regular users cannot access a copy audit history', function () {
    $library = auditLibrary();
    $member = auditMember($library);
    $book = Book::factory()->for($library)->create();
    $copy = BookCopy::factory()->for($library)->for($book)->create();

    $this->actingAs($member)
        ->getJson("/api/v1/book-copies/{$copy->id}/history")
        ->assertForbidden();
});

test('audit history is scoped to the requested copy', function () {
    $library = auditLibrary();
    $member = auditMember($library);
    $staff = auditStaff($library);
    $book = Book::factory()->for($library)->create();
    $firstCopy = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '1']);
    $secondCopy = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '2']);

    app(ReservationService::class)->create($member, $book);

    $this->actingAs($staff)
        ->getJson("/api/v1/book-copies/{$firstCopy->id}/history")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($staff)
        ->getJson("/api/v1/book-copies/{$secondCopy->id}/history")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
