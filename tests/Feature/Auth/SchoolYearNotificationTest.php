<?php

use App\Enums\LibraryRole;
use App\Enums\SchoolYearNotificationType;
use App\Jobs\SendSchoolYearNotification;
use App\Mail\SchoolYearNotificationMail;
use App\Models\Library;
use App\Models\NotificationDelivery;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\GlobalSettings;
use App\Services\SchoolYearService;
use Illuminate\Support\Facades\Bus;

function schoolYearNotificationLibrary(): Library
{
    return Library::factory()->create();
}

function schoolYearNotificationStaff(Library $library, LibraryRole $role): User
{
    return User::factory()->libraryRole($library, $role)->create();
}

function schoolYearNotificationYear(int $year, string $startsOn, string $endsOn): SchoolYear
{
    return SchoolYear::factory()->create([
        'year' => $year,
        'starts_on' => $startsOn,
        'ends_on' => $endsOn,
    ]);
}

test('upcoming school year notification goes to staff and superadmins only once', function () {
    Bus::fake();

    $library = schoolYearNotificationLibrary();
    $librarian = schoolYearNotificationStaff($library, LibraryRole::Librarian);
    $libraryAdmin = schoolYearNotificationStaff($library, LibraryRole::LibraryAdmin);
    $superAdmin = User::factory()->superAdmin()->create();
    $member = schoolYearNotificationStaff($library, LibraryRole::User);

    $active = schoolYearNotificationYear(2026, now()->subYear()->toDateString(), now()->addMonths(6)->toDateString());
    $next = schoolYearNotificationYear(2027, now()->addDays(7)->toDateString(), now()->addYear()->toDateString());

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, true);
    $settings->flush();

    $this->artisan('school-years:notify-upcoming')->assertSuccessful();

    Bus::assertDispatchedTimes(SendSchoolYearNotification::class, 3);
    Bus::assertDispatched(SendSchoolYearNotification::class, fn (SendSchoolYearNotification $job): bool => $job->mail->recipient->is($librarian));
    Bus::assertDispatched(SendSchoolYearNotification::class, fn (SendSchoolYearNotification $job): bool => $job->mail->recipient->is($libraryAdmin));
    Bus::assertDispatched(SendSchoolYearNotification::class, fn (SendSchoolYearNotification $job): bool => $job->mail->recipient->is($superAdmin));
    Bus::assertNotDispatched(SendSchoolYearNotification::class, fn (SendSchoolYearNotification $job): bool => $job->mail->recipient->is($member));
    Bus::assertDispatched(SendSchoolYearNotification::class, fn (SendSchoolYearNotification $job): bool => $job->mail->type === SchoolYearNotificationType::UpcomingActivation);

    expect(NotificationDelivery::query()->count())->toBe(3);

    $this->artisan('school-years:notify-upcoming')->assertSuccessful();

    Bus::assertDispatchedTimes(SendSchoolYearNotification::class, 3);
    expect(NotificationDelivery::query()->count())->toBe(3);
});

test('upcoming school year notification is skipped when automatic activation is disabled', function () {
    Bus::fake();

    $active = schoolYearNotificationYear(2026, now()->subYear()->toDateString(), now()->addMonths(6)->toDateString());
    schoolYearNotificationYear(2027, now()->addDays(7)->toDateString(), now()->addYear()->toDateString());

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, false);
    $settings->flush();

    $this->artisan('school-years:notify-upcoming')->assertSuccessful();

    Bus::assertNothingDispatched();
    expect(NotificationDelivery::query()->count())->toBe(0);
});

test('activating a school year notifies staff and superadmins', function () {
    Bus::fake();

    $library = schoolYearNotificationLibrary();
    $librarian = schoolYearNotificationStaff($library, LibraryRole::Librarian);
    $superAdmin = User::factory()->superAdmin()->create();
    $previous = schoolYearNotificationYear(2026, '2026-09-01', '2027-08-31');
    $next = schoolYearNotificationYear(2027, '2027-09-01', '2028-08-31');

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $previous->id);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, false);
    $settings->flush();

    app(SchoolYearService::class)->activate($next);

    Bus::assertDispatchedTimes(SendSchoolYearNotification::class, 2);
    Bus::assertDispatched(SendSchoolYearNotification::class, fn (SendSchoolYearNotification $job): bool => $job->mail->type === SchoolYearNotificationType::Activated
        && $job->mail->recipient->is($librarian)
        && $job->mail->data['active_year']->is($next)
        && $job->mail->data['previous_year']->is($previous)
    );
    Bus::assertDispatched(SendSchoolYearNotification::class, fn (SendSchoolYearNotification $job): bool => $job->mail->type === SchoolYearNotificationType::Activated
        && $job->mail->recipient->is($superAdmin)
    );
});

test('upcoming school year notification is skipped outside the seven day window', function () {
    Bus::fake();

    $library = schoolYearNotificationLibrary();
    schoolYearNotificationStaff($library, LibraryRole::Librarian);

    $active = schoolYearNotificationYear(2026, now()->subYear()->toDateString(), now()->addMonths(6)->toDateString());
    schoolYearNotificationYear(2027, now()->addDays(8)->toDateString(), now()->addYear()->toDateString());

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, true);
    $settings->flush();

    $this->artisan('school-years:notify-upcoming')->assertSuccessful();

    Bus::assertNothingDispatched();
    expect(NotificationDelivery::query()->count())->toBe(0);
});

test('the upcoming school year email renders the year and activation window', function () {
    app()->setLocale('en');

    $recipient = User::factory()->create();
    $active = schoolYearNotificationYear(2026, now()->subYear()->toDateString(), now()->addMonths(6)->toDateString());
    $next = schoolYearNotificationYear(2027, now()->addDays(7)->toDateString(), now()->addYear()->toDateString());

    $mail = new SchoolYearNotificationMail(
        $recipient,
        SchoolYearNotificationType::UpcomingActivation,
        [
            'next_year' => $next,
            'active_year' => $active,
            'days_until' => 7,
            'name' => $next->name,
            'days' => 7,
        ],
    );

    $html = $mail->render();

    expect($mail->envelope()->subject)->toBe('New school year '.$next->name.' starts in 7 days')
        ->and($html)->toContain($next->name)
        ->toContain('New school year')
        ->toContain('7');
});
