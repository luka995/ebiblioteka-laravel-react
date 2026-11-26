<?php

namespace App\DTOs;

use App\Models\Book;

final readonly class BookImportResolutionDTO
{
    /**
     * @param  array<string, string|int|list<string>|null>  $payload
     * @param  array<string, string>  $sources
     * @param  array<string, mixed>  $diagnostics
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $inputSources
     */
    public function __construct(
        public array $payload,
        public array $sources,
        public array $diagnostics = [],
        public ?Book $existingBook = null,
        public array $input = [],
        public array $inputSources = [],
    ) {}
}
