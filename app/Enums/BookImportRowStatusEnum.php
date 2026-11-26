<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;
use Override;

enum BookImportRowStatusEnum: string
{
    use EnumToArray;

    case Pending = 'pending';
    case Processing = 'processing';
    case Imported = 'imported';
    case Failed = 'failed';

    #[Override]
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('book_imports.row_status.pending'),
            self::Processing => __('book_imports.row_status.processing'),
            self::Imported => __('book_imports.row_status.imported'),
            self::Failed => __('book_imports.row_status.failed'),
        };
    }
}
