<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Status generisanja inventarne knjige.
 *
 * Zamenjuje legacy par flagova `status`/`working` iz tabele `inventar_books`.
 */
enum InventoryBookStatus: string
{
    use EnumToArray;

    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('inventory.status.pending'),
            self::Processing => __('inventory.status.processing'),
            self::Completed => __('inventory.status.completed'),
            self::Failed => __('inventory.status.failed'),
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
