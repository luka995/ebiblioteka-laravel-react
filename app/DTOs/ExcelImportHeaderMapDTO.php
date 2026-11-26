<?php

namespace App\DTOs;

final readonly class ExcelImportHeaderMapDTO
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

    public function hasColumn(string $field): bool
    {
        return isset($this->columnMap[$field]);
    }
}
