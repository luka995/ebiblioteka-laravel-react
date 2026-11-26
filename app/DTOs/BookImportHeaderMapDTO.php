<?php

namespace App\DTOs;

final readonly class BookImportHeaderMapDTO
{
    /**
     * @param  array<int, string|null>  $headers
     * @param  array<string, int>  $columnMap
     */
    public function __construct(
        public string $sheetName,
        public int $rowNumber,
        public array $headers,
        public array $columnMap,
    ) {}

    public function hasInventoryColumn(): bool
    {
        return isset($this->columnMap['inventory_number']);
    }
}
