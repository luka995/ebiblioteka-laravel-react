<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Status zahteva za otvaranje biblioteke škole.
 */
enum LibraryRequestStatus: string
{
    use EnumToArray;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'На чекању',
            self::Approved => 'Одобрен',
            self::Rejected => 'Одбијен',
        };
    }
}
