<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;
use Override;

enum BookImportFailureStageEnum: string
{
    use EnumToArray;

    case Preparation = 'preparation';
    case Processing = 'processing';

    #[Override]
    public function getLabel(): string
    {
        return match ($this) {
            self::Preparation => __('book_imports.failure_stages.preparation'),
            self::Processing => __('book_imports.failure_stages.processing'),
        };
    }
}
