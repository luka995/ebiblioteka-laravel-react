<?php

namespace App\Services\BookImports;

use App\DTOs\BookImportRowDataDTO;
use App\DTOs\ExcelImportHeaderMapDTO;
use App\Services\ExcelImports\ExcelImportMapper;

/**
 * @implements ExcelImportMapper<BookImportRowDataDTO>
 */
final class BookImportMapper implements ExcelImportMapper
{
    public function __construct(
        private readonly BookImportHeaderMapper $headers,
        private readonly BookImportRowPayloadMapper $rows,
    ) {}

    public function mapHeader(array $headers, int $rowNumber, string $sheetName): ?ExcelImportHeaderMapDTO
    {
        return $this->headers->map($headers, $rowNumber, $sheetName);
    }

    public function mapRow(array $cells, int $rowNumber, ExcelImportHeaderMapDTO $header): ?BookImportRowDataDTO
    {
        return $this->rows->map($cells, $rowNumber, $header);
    }
}
