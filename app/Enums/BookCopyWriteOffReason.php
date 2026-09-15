<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Razlog otpisa fizicke jedinice (legacy `dismissType`).
 */
enum BookCopyWriteOffReason: string
{
    use EnumToArray;

    case OutOfDate = 'out_of_date';
    case Unusable = 'unusable';

    public function getLabel(): string
    {
        return match ($this) {
            self::OutOfDate => __('books.write_off.reasons.out_of_date'),
            self::Unusable => __('books.write_off.reasons.unusable'),
        };
    }
}
