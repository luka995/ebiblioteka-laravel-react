<?php

namespace App\Http\Requests;

use App\Enums\BookImportRowStatusEnum;

class StartBookImportRequest extends BookImportSelectionRequest
{
    protected function selectableStatus(): string
    {
        return BookImportRowStatusEnum::Pending->value;
    }
}
