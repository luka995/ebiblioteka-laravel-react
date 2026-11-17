<?php

use App\Enums\CirculationNotificationType;
use App\Enums\LibraryRole;
use App\Enums\ReservationStatus;
use App\Jobs\SendCirculationNotification;
use App\Mail\CirculationNotificationMail;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\NotificationDelivery;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BorrowService;
use App\Services\Mail\TransactionalMailService;
use App\Services\Notification\NotificationDeliveryService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

function notificationLibrary(): Library
{
    return Library::factory()->create();
}

function notificationMember(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id, ['role' => LibraryRole::User->value]);

    return $user;
}

function notificationStaff(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library->id, ['role' => LibraryRole::Librarian->value]);

    return $user;
}

function notificationBook(Library $library): Book
{
    return Book::factory()->for($library)->create();
}

function notificationCopy(Book $book, array $attributes = []): BookCopy
{
    return BookCopy::factory()->for($book->library)->for($book)->create($attributes);
}

function sentTo(User $user): Closure
{
    return fn (SendCirculationNotification $job): bool => $job->mail->recipient->is($user);
}

function sentType(CirculationNotificationType $type): Closure
{
    return fn (SendCirculationNotification $job): bool => $job->mail->type === $type;
}

test('circulation notification job marks deliveries sent after sending the mail', function () {
    Mail::fake();

    $recipient = User::factory()->create();
    $delivery = app(NotificationDeliveryService::class)->claim(
        CirculationNotificationType::LoanDueTodayUser,
        'loan-due:test',
        $recipient,
    );
    $job = new SendCirculationNotification(
        [$delivery->id],
        new CirculationNotificationMail($recipient, CirculationNotificationType::LoanDueTodayUser),
    );
    $job = unserialize(serialize($job));

    $job->handle(app(TransactionalMailService::class), app(NotificationDeliveryService::class));

    Mail::assertSent(CirculationNotificationMail::class, 1);
    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SENT)
        ->and($delivery->fresh()->sent_at)->not->toBeNull();
});

test('circulation notification job marks deliveries failed after queue retries are exhausted', function () {
    $recipient = User::factory()->create();
    $delivery = app(NotificationDeliveryService::class)->claim(
        CirculationNotificationType::LoanDueTodayUser,
        'loan-due:test-failure',
        $recipient,
    );
    $job = new SendCirculationNotification(
        [$delivery->id],
        new CirculationNotificationMail($recipient, CirculationNotificationType::LoanDueTodayUser),
    );

    $job->failed(new RuntimeException('Mail transport failed.'));

    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_FAILED)
        ->and($delivery->fresh()->error)->toBe('Mail transport failed.');
});

test('a new reservation notifies both the user and library staff', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $staff = notificationStaff($library);
    $book = notificationBook($library);
    notificationCopy($book);

    $reservation = app(ReservationService::class)->create($member, $book);

    Bus::assertDispatched(SendCirculationNotification::class, 2);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::ReservationCreatedUser));
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::ReservationCreatedStaff));
    Bus::assertDispatched(SendCirculationNotification::class, sentTo($member));
    Bus::assertDispatched(SendCirculationNotification::class, sentTo($staff));

    expect(NotificationDelivery::query()->count())->toBe(2)
        ->and($reservation->status)->toBe(ReservationStatus::Active);
});

test('superadmins are treated as library staff notification recipients', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $staff = notificationStaff($library);
    $superAdmin = User::factory()->superAdmin()->create();
    $superAdmin->libraries()->attach($library->id, ['role' => LibraryRole::LibraryAdmin->value]);
    $book = notificationBook($library);
    notificationCopy($book);

    app(ReservationService::class)->create($member, $book);

    Bus::assertDispatched(SendCirculationNotification::class, sentTo($staff));
    Bus::assertDispatched(SendCirculationNotification::class, sentTo($superAdmin));
});

test('cancellation by the owner notifies the user and library staff', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $staff = notificationStaff($library);
    $book = notificationBook($library);
    notificationCopy($book);

    $reservation = app(ReservationService::class)->create($member, $book);
    NotificationDelivery::query()->delete();
    Bus::fake();

    app(ReservationService::class)->cancel($reservation, $member);

    Bus::assertDispatched(SendCirculationNotification::class, 2);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::ReservationCancelledUser));
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::ReservationCancelledStaff));
    Bus::assertDispatched(SendCirculationNotification::class, sentTo($staff));
});

test('cancellation by staff only notifies the user', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $staff = notificationStaff($library);
    $book = notificationBook($library);
    notificationCopy($book);

    $reservation = app(ReservationService::class)->create($member, $book);
    NotificationDelivery::query()->delete();
    Bus::fake();

    app(ReservationService::class)->cancel($reservation, $staff);

    Bus::assertDispatched(SendCirculationNotification::class, 1);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::ReservationCancelledUser));
});

test('issuing a loan notifies only the user by default', function () {
    Bus::fake();

    $library = notificationLibrary();
    $staff = notificationStaff($library);
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book);

    $borrow = app(BorrowService::class)->issue($staff, $library, $member, $copy);

    Bus::assertDispatched(SendCirculationNotification::class, 1);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::LoanIssuedUser));
    Bus::assertDispatched(SendCirculationNotification::class, sentTo($member));
    expect($borrow->returned)->toBeFalse();
});

test('staff can opt in to issue confirmation emails', function () {
    Bus::fake();

    $library = notificationLibrary();
    $library->settings()->update(['notify_staff_on_issue' => true]);
    $staff = notificationStaff($library);
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book);

    app(BorrowService::class)->issue($staff, $library, $member, $copy);

    Bus::assertDispatched(SendCirculationNotification::class, 2);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::LoanIssuedUser));
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::LoanIssuedStaff));
});

test('returning a loan notifies the user and optionally staff', function () {
    Bus::fake();

    $library = notificationLibrary();
    $library->settings()->update(['notify_staff_on_return' => true]);
    $staff = notificationStaff($library);
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book);

    $borrow = app(BorrowService::class)->issue($staff, $library, $member, $copy);
    NotificationDelivery::query()->delete();
    Bus::fake();

    app(BorrowService::class)->returnBook($staff, $borrow);

    Bus::assertDispatched(SendCirculationNotification::class, 2);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::LoanReturnedUser));
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::LoanReturnedStaff));
});

test('reservation expiry reminder is sent only once', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book, ['reserved' => true]);

    Reservation::factory()->forCopy($copy, $member)->create([
        'valid_till' => now()->addHours(2),
    ]);

    $this->artisan('notifications:reservation-expiring')->assertSuccessful();
    $this->artisan('notifications:reservation-expiring')->assertSuccessful();

    Bus::assertDispatched(SendCirculationNotification::class, 1);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::ReservationExpiringUser));
    expect(NotificationDelivery::query()->count())->toBe(1);
});

test('an expired reservation notifies the user once', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book, ['reserved' => true]);

    Reservation::factory()->forCopy($copy, $member)->create([
        'valid_till' => now()->subMinute(),
    ]);

    $this->artisan('reservations:expire')->assertSuccessful();
    $this->artisan('reservations:expire')->assertSuccessful();

    Bus::assertDispatched(SendCirculationNotification::class, 1);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::ReservationExpiredUser));
    expect(NotificationDelivery::query()->count())->toBe(1);
});

test('due-soon user reminder is aggregated and not repeated', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $book = notificationBook($library);
    $firstCopy = notificationCopy($book);
    $secondCopy = notificationCopy($book);

    Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $firstCopy->id,
        'from_date' => now()->subDays(10),
        'valid_till' => now()->addDays(3),
        'returned' => false,
    ]);

    Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $secondCopy->id,
        'from_date' => now()->subDays(10),
        'valid_till' => now()->addDays(3),
        'returned' => false,
    ]);

    $this->artisan('notifications:user-loans-due')->assertSuccessful();
    $this->artisan('notifications:user-loans-due')->assertSuccessful();

    Bus::assertDispatched(SendCirculationNotification::class, 1);
    Bus::assertDispatched(SendCirculationNotification::class, sentType(CirculationNotificationType::LoanDueSoonUser));
    Bus::assertDispatched(SendCirculationNotification::class, fn (SendCirculationNotification $job): bool => $job->queue === 'emails'
        && count($job->deliveryIds) === 2);
    expect(NotificationDelivery::query()->count())->toBe(2);
    expect(NotificationDelivery::query()->where('status', NotificationDelivery::STATUS_QUEUED)->count())->toBe(2);
});

test('staff daily digest contains loans due in three days', function () {
    Bus::fake();

    $library = notificationLibrary();
    $staff = notificationStaff($library);
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book);

    Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'from_date' => now()->subDays(10),
        'valid_till' => now()->addDays(3),
        'returned' => false,
    ]);

    $this->artisan('notifications:staff-daily-digest')->assertSuccessful();

    Bus::assertDispatched(SendCirculationNotification::class, 1);
    Bus::assertDispatched(SendCirculationNotification::class, function (SendCirculationNotification $job) use ($staff): bool {
        return $job->mail->recipient->is($staff)
            && $job->mail->type === CirculationNotificationType::StaffDailyDigest
            && $job->mail->data['loans_due']->count() === 1;
    });
});

test('notifications are not sent for returned loans', function () {
    Bus::fake();

    $library = notificationLibrary();
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book);

    Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'from_date' => now()->subDays(10),
        'valid_till' => now()->addDays(3),
        'returned' => true,
        'return_date' => now(),
    ]);

    $this->artisan('notifications:user-loans-due')->assertSuccessful();

    Bus::assertNothingDispatched();
});

test('notification email templates render with circulation data', function () {
    $library = notificationLibrary();
    $member = notificationMember($library);
    $book = notificationBook($library);
    $copy = notificationCopy($book);

    $loan = Borrow::create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'from_date' => now()->subDay(),
        'valid_till' => now()->addDays(10),
        'returned' => false,
    ]);

    $loan->load(['user', 'book.authors', 'bookCopy', 'library.settings']);

    $mail = new CirculationNotificationMail($member, CirculationNotificationType::LoanIssuedUser, [
        'loan' => $loan,
        'book' => $book,
        'copy' => $copy,
        'library' => $library,
        'user' => $member,
    ]);

    expect($mail->render())->toContain($book->name);

    $digest = new CirculationNotificationMail($member, CirculationNotificationType::StaffDailyDigest, [
        'library' => $library,
        'loans_due' => collect([$loan]),
        'loans_overdue' => collect(),
        'reservations_expiring' => collect(),
    ]);

    expect($digest->render())->toContain($book->name);
});

test('circulation mail falls back to a supported locale instead of raw translation keys', function () {
    app()->setLocale('sr');

    $recipient = User::factory()->create(['locale' => null]);
    $mail = new CirculationNotificationMail(
        $recipient,
        CirculationNotificationType::ReservationCreatedStaff,
        [],
    );

    app(TransactionalMailService::class)->sendNowFor($recipient, $mail);

    $transport = Mail::mailer('array')->getSymfonyTransport();
    $message = $transport->messages()->last()->getOriginalMessage();
    $html = (string) $message->getHtmlBody();

    expect($html)
        ->not->toContain('emails.circulation.')
        ->not->toContain('emails.brand.')
        ->toContain('Нова резервација');

    app()->setLocale('en');
});
