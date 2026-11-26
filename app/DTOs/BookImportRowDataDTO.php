<?php

namespace App\DTOs;

use App\Enums\BookImportFailureEnum;

final readonly class BookImportRowDataDTO
{
    /**
     * @param  array<string, string|int|list<string>|null>  $payload
     */
    public function __construct(
        public int $rowNumber,
        public array $payload,
        public ?BookImportFailureEnum $failureCode = null,
        public ?string $failureField = null,
    ) {}
}
