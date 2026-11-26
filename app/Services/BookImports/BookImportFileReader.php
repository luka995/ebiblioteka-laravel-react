<?php

namespace App\Services\BookImports;

use App\DTOs\BookImportHeaderMapDTO;
use App\DTOs\BookImportRowDataDTO;
use App\Enums\BookImportFailureEnum;
use App\Exceptions\BookImportFileException;
use Generator;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

class BookImportFileReader
{
    public function __construct(
        private readonly BookImportHeaderMapper $headers,
        private readonly BookImportRowPayloadMapper $payloads,
    ) {}

    public function header(string $path): BookImportHeaderMapDTO
    {
        $reader = $this->reader();

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number > config('book_imports.header_scan_rows')) {
                        break;
                    }

                    $header = $this->headers->map(
                        array_map($this->payloads->text(...), $row->toArray()),
                        $number,
                        $sheet->getName(),
                    );

                    if ($header !== null) {
                        return $header;
                    }
                }

                break;
            }
        } catch (OpenSpoutException $e) {
            throw new BookImportFileException(BookImportFailureEnum::FileUnreadable, $e);
        } finally {
            $reader->close();
        }

        throw new BookImportFileException(BookImportFailureEnum::MissingColumns);
    }

    /**
     * @return Generator<int, BookImportRowDataDTO>
     */
    public function rows(string $path, BookImportHeaderMapDTO $header): Generator
    {
        $reader = $this->reader();

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number <= $header->rowNumber) {
                        continue;
                    }

                    $payload = $this->payloads->map($row->getCells(), $number, $header);

                    if ($payload !== null) {
                        yield $number => $payload;
                    }
                }

                break;
            }
        } catch (OpenSpoutException $e) {
            throw new BookImportFileException(BookImportFailureEnum::FileUnreadable, $e);
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
