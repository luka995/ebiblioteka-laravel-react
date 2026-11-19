<?php

namespace App\Services\Notification;

use App\Enums\LibraryRole;
use App\Enums\SchoolYearNotificationType;
use App\Jobs\SendSchoolYearNotification;
use App\Mail\SchoolYearNotificationMail;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\GlobalSettings;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Email obavestenja o skolskim godinama.
 *
 * Primaoci su iskljucivo bibliotekari, administratori biblioteka i
 * superadmini. Deduplikacija ide preko notification_deliveries.
 */
class SchoolYearNotificationService
{
    public function __construct(
        private readonly NotificationDeliveryService $deliveries,
        private readonly GlobalSettings $settings,
    ) {}

    /**
     * Salje podsetnik da se nova godina automatski aktivira za :days dana.
     *
     * @return int broj novih delivery zapisa
     */
    public function upcomingActivation(SchoolYear $nextYear, SchoolYear $activeYear, int $daysUntil): int
    {
        $type = SchoolYearNotificationType::UpcomingActivation;
        $sent = 0;

        // Each recipient receives at most one reminder per school year, even if the command runs daily.
        foreach ($this->recipients() as $recipient) {
            $delivery = $this->deliveries->claimFor(
                $type->value,
                "school-year-upcoming:{$nextYear->id}:{$recipient->id}",
                $recipient,
                null,
                $nextYear,
                [
                    'days_until' => $daysUntil,
                    'active_school_year_id' => $activeYear->id,
                ],
            );

            if ($delivery === null) {
                continue;
            }

            try {
                SendSchoolYearNotification::dispatch(
                    [$delivery->id],
                    new SchoolYearNotificationMail($recipient, $type, [
                        'next_year' => $nextYear,
                        'active_year' => $activeYear,
                        'days_until' => $daysUntil,
                        'name' => $nextYear->name,
                        'days' => $daysUntil,
                    ]),
                );
                $sent++;
            } catch (Throwable $exception) {
                $this->deliveries->markFailed($delivery, $exception->getMessage());
                report($exception);
            }
        }

        return $sent;
    }

    public function activated(SchoolYear $activeYear, ?SchoolYear $previousYear = null): int
    {
        $type = SchoolYearNotificationType::Activated;
        $sent = 0;

        foreach ($this->recipients() as $recipient) {
            $delivery = $this->deliveries->claimFor(
                $type->value,
                "school-year-activated:{$activeYear->id}:{$recipient->id}",
                $recipient,
                null,
                $activeYear,
                [
                    'active_school_year_id' => $activeYear->id,
                    'previous_school_year_id' => $previousYear?->id,
                ],
            );

            if ($delivery === null) {
                continue;
            }

            try {
                SendSchoolYearNotification::dispatch(
                    [$delivery->id],
                    new SchoolYearNotificationMail($recipient, $type, [
                        'active_year' => $activeYear,
                        'previous_year' => $previousYear,
                        'name' => $activeYear->name,
                    ]),
                );
                $sent++;
            } catch (Throwable $exception) {
                $this->deliveries->markFailed($delivery, $exception->getMessage());
                report($exception);
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(): Collection
    {
        $staff = User::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->whereHas('libraries', function ($query): void {
                $query->where('libraries.deleted', false)
                    ->whereIn('library_user.role', [
                        LibraryRole::Librarian->value,
                        LibraryRole::LibraryAdmin->value,
                    ]);
            })
            ->get();

        $superAdmins = User::query()
            ->whereIn('id', $this->settings->superAdminIds())
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->get();

        return $staff->merge($superAdmins)
            ->unique('id')
            ->values();
    }
}
