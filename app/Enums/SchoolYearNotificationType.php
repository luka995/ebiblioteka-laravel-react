<?php

namespace App\Enums;

/**
 * Tipovi email notifikacija vezanih za skolske godine.
 */
enum SchoolYearNotificationType: string
{
    case UpcomingActivation = 'school_year_upcoming_activation';

    case Activated = 'school_year_activated';

    public function subjectKey(): string
    {
        return match ($this) {
            self::UpcomingActivation => 'emails.school_year.upcoming.subject',
            self::Activated => 'emails.school_year.activated.subject',
        };
    }

    public function view(): string
    {
        return match ($this) {
            self::UpcomingActivation => 'emails.school-year.upcoming',
            self::Activated => 'emails.school-year.activated',
        };
    }

    public function textView(): string
    {
        return match ($this) {
            self::UpcomingActivation => 'emails.school-year.upcoming-text',
            self::Activated => 'emails.school-year.activated-text',
        };
    }
}
