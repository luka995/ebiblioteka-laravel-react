<?php

namespace App\DTOs;

use App\Enums\IsbnLookupStatusEnum;
use App\Services\Isbn\BookMetadata;
use InvalidArgumentException;

final readonly class IsbnLookupResultDTO
{
    /**
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        public IsbnLookupStatusEnum $status,
        public ?BookMetadata $metadata,
        public array $diagnostics,
    ) {
        if (($status === IsbnLookupStatusEnum::Found) !== ($metadata !== null)) {
            throw new InvalidArgumentException('Only a found ISBN result must contain metadata.');
        }
    }
}
