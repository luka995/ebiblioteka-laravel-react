<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Status generisanja PDF-a sa bar-kodovima jedinica biblioteke.
 */
enum BarcodePrintJobStatus: string
{
    use EnumToArray;

    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('barcode.status.pending'),
            self::Processing => __('barcode.status.processing'),
            self::Completed => __('barcode.status.completed'),
            self::Failed => __('barcode.status.failed'),
        };
    }

    /**
     * Da li je proces zavrsen (uspesno ili neuspesno).
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }

    /**
     * Da li je generisanje jos u toku (za polling).
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
