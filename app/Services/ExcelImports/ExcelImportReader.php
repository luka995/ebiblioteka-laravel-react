<?php

namespace App\Services\ExcelImports;

use App\DTOs\ExcelImportHeaderMapDTO;
use Generator;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

class ExcelImportReader
{
    public function __construct(private readonly ExcelCellNormalizer $cells) {}

    /**
     * @param  ExcelImportMapper<object>  $mapper
     *
     * @throws OpenSpoutException
     */
    public function header(string $path, ExcelImportMapper $mapper, int $scanRows): ?ExcelImportHeaderMapDTO
    {
        $reader = $this->reader();

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number > $scanRows) {
                        break;
                    }

                    $header = $mapper->mapHeader(
                        array_map($this->cells->text(...), $row->toArray()),
                        $number,
                        $sheet->getName(),
                    );

                    if ($header !== null) {
                        return $header;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    /**
     * @template TRow of object
     *
     * @param  ExcelImportMapper<TRow>  $mapper
     * @return Generator<int, TRow>
     *
     * @throws OpenSpoutException
     */
    public function rows(string $path, ExcelImportHeaderMapDTO $header, ExcelImportMapper $mapper): Generator
    {
        $reader = $this->reader();

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number <= $header->rowNumber) {
                        continue;
                    }

                    $payload = $mapper->mapRow($row->getCells(), $number, $header);

                    if ($payload !== null) {
                        yield $number => $payload;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    private function reader(): Reader
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new Reader($options);
    }
}
