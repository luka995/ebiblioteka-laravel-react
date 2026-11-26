<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;
use Override;

enum BookImportModeEnum: string
{
    use EnumToArray;

    case IsbnCheck = 'isbn_check';
    case ExcelDirect = 'excel_direct';

    #[Override]
    public function getLabel(): string
    {
        return match ($this) {
            self::IsbnCheck => __('book_imports.modes.isbn_check'),
            self::ExcelDirect => __('book_imports.modes.excel_direct'),
        };
    }
}
