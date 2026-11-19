<?php

namespace App\Services;

use App\Models\SchoolYear;
use App\Models\User;
use App\Services\Notification\SchoolYearNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aktivna skolska godina i zivotni ciklus skolskih godina.
 *
 * Aktivna godina se cuva u settings tabeli.
 */
class SchoolYearService
{
    public function __construct(
        private readonly GlobalSettings $settings,
        private readonly SchoolYearAutoCreationService $autoCreation,
        private readonly SchoolYearNotificationService $notifications,
        private ?SchoolYear $activeYear = null,
        private bool $activeLoaded = false,
    ) {}

    public function active(): ?SchoolYear
    {
        if (! $this->activeLoaded) {
            $id = (int) $this->settings->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID);

            $this->activeYear = $id > 0
                ? SchoolYear::query()->find($id)
                : null;
            $this->activeLoaded = true;
        }

        return $this->activeYear;
    }

    public function activeId(): ?int
    {
        return $this->active()?->id;
    }

    public function isActive(SchoolYear $year): bool
    {
        return $this->active()?->is($year) ?? false;
    }

    public function requireActive(): SchoolYear
    {
        $active = $this->active();

        if ($active === null) {
            throw ValidationException::withMessages([
                'school_year_id' => __('validation.custom.active_school_year_required'),
            ]);
        }

        return $active;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): SchoolYear
    {
        return SchoolYear::query()->create([
            'year' => (int) $data['year'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'created_by_id' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SchoolYear $year, array $data): SchoolYear
    {
        if ((int) $data['year'] !== $year->year && $year->classes()->exists()) {
            throw ValidationException::withMessages([
                'year' => __('validation.custom.school_year_year_locked'),
            ]);
        }

        $year->update([
            'year' => (int) $data['year'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
        ]);

        return $year->refresh();
    }

    public function delete(SchoolYear $year): void
    {
        if ($this->isActive($year)) {
            throw ValidationException::withMessages([
                'school_year' => __('validation.custom.school_year_active_delete'),
            ]);
        }

        if (
            $year->classes()->exists()
            || $year->classEnrollments()->exists()
            || $year->classroomHistory()->exists()
        ) {
            throw ValidationException::withMessages([
                'school_year' => __('validation.custom.school_year_has_classes'),
            ]);
        }

        $year->delete();
    }

    /**
     * Aktivira godinu i obavestava primaoce o promeni aktivne godine.
     *
     * Kada je ukljucena automatska aktivacija, rucna aktivacija je zabranjena
     * osim kada je poziva cron (force = true).
     */
    public function activate(SchoolYear $year, ?User $actor = null, bool $force = false): SchoolYear
    {
        if (! $force && $this->automaticActivationEnabled()) {
            throw ValidationException::withMessages([
                'school_year' => __('validation.custom.school_year_auto_activation_enabled'),
            ]);
        }

        $previousYear = $this->active();

        if ($previousYear?->is($year)) {
            return $year;
        }

        try {
            DB::transaction(function () use ($year, $actor): void {
                $this->settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $year->id, $actor);
            });
        } finally {
            $this->flushActive();
        }

        $activated = $year->refresh();
        $this->notifications->activated($activated, $previousYear);

        return $activated;
    }

    public function automaticActivationEnabled(): bool
    {
        return (bool) $this->settings->get(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, false);
    }

    public function setAutomaticActivation(bool $enabled, ?User $actor = null): ?SchoolYear
    {
        try {
            DB::transaction(function () use ($enabled, $actor): void {
                $this->settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, $enabled, $actor);

                if ($enabled && ($active = $this->active()) !== null) {
                    $this->autoCreation->ensureNextYear($actor);
                }
            });
        } finally {
            $this->flushActive();
        }

        return $this->active();
    }

    public function nextYear(?SchoolYear $year = null): ?SchoolYear
    {
        $year ??= $this->active();

        if ($year === null) {
            return null;
        }

        return SchoolYear::query()
            ->where('year', $year->year + 1)
            ->first();
    }

    /**
     * Cron ulaz: aktivira godinu koja pocinje danas, kreirajuci je ako ne postoji.
     */
    public function activateDueYears(?User $actor = null): ?SchoolYear
    {
        if (! $this->automaticActivationEnabled()) {
            return null;
        }

        $startsOn = today();
        $year = SchoolYear::query()
            ->whereDate('starts_on', $startsOn)
            ->first();

        if ($year === null) {
            $year = SchoolYear::query()->createOrFirst(
                ['year' => $startsOn->year],
                [
                    'starts_on' => $startsOn->toDateString(),
                    'ends_on' => Carbon::create($startsOn->year + 1, 8, 31)->toDateString(),
                    'created_by_id' => $actor?->id,
                ],
            );
        }

        return $this->activate($year, $actor, force: true);
    }

    private function flushActive(): void
    {
        $this->activeLoaded = false;
        $this->activeYear = null;
        $this->settings->flush();
    }
}
