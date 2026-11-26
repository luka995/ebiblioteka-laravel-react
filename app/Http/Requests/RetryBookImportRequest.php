<?php

namespace App\Http\Requests;

use App\Enums\BookImportRowStatusEnum;

class RetryBookImportRequest extends BookImportSelectionRequest
{
    protected function selectableStatus(): string
    {
        return BookImportRowStatusEnum::Failed->value;
    }
}
