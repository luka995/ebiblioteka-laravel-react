<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;
use Override;

enum IsbnLookupStatusEnum: string
{
    use EnumToArray;

    case Found = 'found';
    case NotFound = 'not_found';
    case Failed = 'failed';

    #[Override]
    public function getLabel(): string
    {
        return __('book_imports.lookup_status.'.$this->value);
    }
}
