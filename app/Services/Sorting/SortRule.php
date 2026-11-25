<?php

namespace App\Services\Sorting;

final readonly class SortRule
{
    public function __construct(
        public string $column,
        public ?string $direction = null,
    ) {}
}
