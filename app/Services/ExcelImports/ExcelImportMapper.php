<?php

namespace App\Services\ExcelImports;

use App\DTOs\ExcelImportHeaderMapDTO;
use OpenSpout\Common\Entity\Cell;

/**
 * @template-covariant TRow of object
 */
interface ExcelImportMapper
{
    /**
     * @param  array<int, string|null>  $headers
     */
    public function mapHeader(array $headers, int $rowNumber, string $sheetName): ?ExcelImportHeaderMapDTO;

    /**
     * @param  array<int, Cell>  $cells
     * @return TRow|null
     */
    public function mapRow(array $cells, int $rowNumber, ExcelImportHeaderMapDTO $header): ?object;
}
