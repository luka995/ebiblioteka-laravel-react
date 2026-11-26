<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;
use Override;

enum BookImportStatusEnum: string
{
    use EnumToArray;

    case Pending = 'pending';
    case Ready = 'ready';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    #[Override]
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('book_imports.status.pending'),
            self::Ready => __('book_imports.status.ready'),
            self::Processing => __('book_imports.status.processing'),
            self::Completed => __('book_imports.status.completed'),
            self::CompletedWithErrors => __('book_imports.status.completed_with_errors'),
            self::Failed => __('book_imports.status.failed'),
        };
    }
}
