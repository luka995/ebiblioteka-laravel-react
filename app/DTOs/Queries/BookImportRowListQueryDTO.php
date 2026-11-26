<?php

namespace App\DTOs\Queries;

use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;

final readonly class BookImportRowListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?BookImportRowStatusEnum $status = null,
        public ?BookImportFailureEnum $failureCode = null,
        public ?BookImportModeEnum $attemptMode = null,
        public ?string $search = null,
        public int $perPage = 200,
    ) {}
}
