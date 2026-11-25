<?php

use App\Enums\BookCopyWriteOffReason;
use App\Enums\LibraryRole;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\User;
use App\Services\BookCopyWriteOffService;

/**
 * @return array{library: Library, staff: User}
 */
function dashboardStatsLibrary(): array
{
    $library = Library::factory()->create();

    $staff = User::factory()->create();
    $staff->libraries()->attach($library->id, ['role' => LibraryRole::Librarian->value]);

    return compact('library', 'staff');
}

function dashboardStatsMember(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id, ['role' => LibraryRole::User->value]);

    return $user;
}

function dashboardStatsCopy(Library $library, Book $book): BookCopy
{
    return BookCopy::factory()
        ->for($library)
        ->for($book)
        ->create();
}

function dashboardStatsBorrow(
    Library $library,
    User $user,
    Book $book,
    BookCopy $copy,
    array $attributes = [],
): Borrow {
    return Borrow::create([
        'library_id' => $library->id,
        'user_id' => $user->id,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'from_date' => now()->subDays(5),
        'valid_till' => now()->addDays(5),
        'returned' => false,
        ...$attributes,
    ]);
}

test('dashboard stats are scoped to the active library', function () {
    ['library' => $library, 'staff' => $staff] = dashboardStatsLibrary();

    $member = dashboardStatsMember($library);
    $otherMember = dashboardStatsMember($library);

    $deactivated = dashboardStatsMember($library);
    $deactivated->libraries()->updateExistingPivot($library->id, ['deleted_at' => now()]);

    $book = Book::factory()->for($library)->create();

    $activeCopy = dashboardStatsCopy($library, $book);
    $overdueCopy = dashboardStatsCopy($library, $book);
    $returnedCopy = dashboardStatsCopy($library, $book);
    dashboardStatsCopy($library, $book);

    $writtenOff = dashboardStatsCopy($library, $book);
    app(BookCopyWriteOffService::class)->writeOff(
        $writtenOff,
        BookCopyWriteOffReason::Unusable,
        null,
        null,
        $staff,
    );

    $archived = dashboardStatsCopy($library, $book);
    $archived->delete();

    dashboardStatsBorrow($library, $member, $book, $activeCopy);
    dashboardStatsBorrow($library, $otherMember, $book, $overdueCopy, [
        'valid_till' => now()->subDays(3)->endOfDay(),
    ]);
    dashboardStatsBorrow($library, $member, $book, $returnedCopy, [
        'returned' => true,
        'return_date' => now(),
        'valid_till' => now()->subDays(10)->endOfDay(),
    ]);

    // Podaci iz druge biblioteke ne smeju da uđu u zbir.
    ['library' => $otherLibrary, 'staff' => $otherStaff] = dashboardStatsLibrary();
    $otherMember = dashboardStatsMember($otherLibrary);
    $otherBook = Book::factory()->for($otherLibrary)->create();
    $otherCopy = dashboardStatsCopy($otherLibrary, $otherBook);
    dashboardStatsCopy($otherLibrary, $otherBook);
    dashboardStatsBorrow($otherLibrary, $otherMember, $otherBook, $otherCopy, [
        'valid_till' => now()->subDay()->endOfDay(),
    ]);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.members_count', 3)
        ->assertJsonPath('data.books_count', 4)
        ->assertJsonPath('data.active_borrows_count', 2)
        ->assertJsonPath('data.overdue_borrows_count', 1);
});

test('dashboard stats require an active library', function () {
    ['library' => $firstLibrary] = dashboardStatsLibrary();
    ['library' => $secondLibrary] = dashboardStatsLibrary();

    $staff = User::factory()->create();
    $staff->libraries()->attach($firstLibrary->id, ['role' => LibraryRole::Librarian->value]);
    $staff->libraries()->attach($secondLibrary->id, ['role' => LibraryRole::LibraryAdmin->value]);

    $this->actingAs($staff)
        ->getJson('/api/v1/dashboard/stats')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('library_id');
});

test('superadmin sees dashboard stats for the selected active library', function () {
    ['library' => $library] = dashboardStatsLibrary();
    $superadmin = User::factory()->superAdmin()->create();

    $this->actingAs($superadmin)
        ->getJson('/api/v1/dashboard/stats')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('library_id');

    $this->actingAs($superadmin)
        ->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.members_count', 1)
        ->assertJsonPath('data.books_count', 0)
        ->assertJsonPath('data.active_borrows_count', 0)
        ->assertJsonPath('data.overdue_borrows_count', 0);
});

test('a regular member cannot see library-wide dashboard stats', function () {
    ['library' => $library] = dashboardStatsLibrary();
    $member = dashboardStatsMember($library);

    $this->actingAs($member)
        ->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/dashboard/stats')
        ->assertForbidden();
});

test('staff cannot see dashboard stats for a library where they are only a member', function () {
    ['library' => $staffLibrary] = dashboardStatsLibrary();
    ['library' => $memberLibrary] = dashboardStatsLibrary();

    $staff = User::factory()->create();
    $staff->libraries()->attach($staffLibrary->id, ['role' => LibraryRole::Librarian->value]);
    $staff->libraries()->attach($memberLibrary->id, ['role' => LibraryRole::User->value]);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $memberLibrary->id])
        ->getJson('/api/v1/dashboard/stats')
        ->assertForbidden();
});
