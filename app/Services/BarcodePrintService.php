<?php

namespace App\Services;

use App\Models\BarcodePrintJob;
use App\Models\BookCopy;
use App\Models\Library;
use App\Support\BarCode;
use App\Support\BarcodeLabel;
use RuntimeException;

/**
 * Priprema nalepnice sa bar-kodovima svih nearhiviranih jedinica biblioteke.
 *
 * Port legacy `BarcodePrintService` (cron `library:generate:barcodeprints`) na
 * Laravel queue: iste jedinice, isti redosled i isto preskakanje neispravnih
 * EAN-13 barkodova; renderovanje prepusta `BarcodePdfService`.
 */
class BarcodePrintService
{
    public function __construct(private readonly BarcodePdfService $pdf) {}

    /**
     * @return array{contents: string, items: int, invalid: int}
     */
    public function render(BarcodePrintJob $job): array
    {
        $library = $job->library;

        if (! $library instanceof Library) {
            throw new RuntimeException("Biblioteka #{$job->library_id} ne postoji.");
        }

        $labels = [];
        $invalid = 0;
        $libraryName = $library->name;

        // Sve nearhivirane jedinice (ukljucujuci otpisane), bez jedinica ciji
        // je naslov arhiviran, u redosledu inventarnog broja.
        $rows = BookCopy::query()
            ->join('books', 'books.id', '=', 'book_copies.book_id')
            ->whereNull('books.deleted_at')
            ->where('book_copies.library_id', $library->id)
            ->whereNotNull('book_copies.barcode')
            ->where('book_copies.barcode', '<>', '')
            ->orderByRaw('CAST(book_copies.order_number AS BIGINT) ASC')
            ->orderBy('book_copies.order_number')
            ->orderBy('book_copies.id')
            ->select(['book_copies.barcode', 'books.name as book_name'])
            ->cursor();

        foreach ($rows as $row) {
            $barcode = (string) $row->barcode;

            // Neispravan EAN-13 se preskace; posao se zbog njega ne obara.
            if (! BarCode::validate($barcode)) {
                $invalid++;

                continue;
            }

            $labels[] = new BarcodeLabel($barcode, $row->book_name, array_filter([$libraryName]));
        }

        if ($labels === []) {
            throw new RuntimeException(__('barcode.errors.no_printable_copies'));
        }

        return [
            'contents' => $this->pdf->render($labels, $job->format),
            'items' => count($labels),
            'invalid' => $invalid,
        ];
    }
}
