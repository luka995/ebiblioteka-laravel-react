<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

enum LoanReminderWindow: string
{
    use EnumToArray;

    case Soon = 'soon';
    case Today = 'today';
    case Overdue = 'overdue';

    public function getLabel(): string
    {
        return match ($this) {
            self::Soon => __('emails.circulation.labels.due_soon'),
            self::Today => __('emails.circulation.labels.due_today'),
            self::Overdue => __('emails.circulation.labels.overdue'),
        };
    }
}
