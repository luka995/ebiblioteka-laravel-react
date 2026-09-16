<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Library;
use Illuminate\Support\Collection;
use TCPDF;

/**
 * Generise PDF inventarne knjige za jednu biblioteku.
 *
 * Direktan port legacy komande `library:generate:inventarbooks`
 * (GenerateInventarBookCommand::printPage) na TCPDF biblioteku koja je vec
 * prisutna u projektu. Layout je landscape A4, 10 redova po strani, sa istim
 * kolonama kao u legacy aplikaciji.
 */
class InventoryBookPdfService
{
    private const MARGIN_TOP = 10;

    private const MARGIN_LEFT = 15;

    private const MARGIN_RIGHT = 10;

    private const ROWS_PER_PAGE = 10;

    private const HEADER_HEIGHT = 14;

    private const ROW_HEIGHT = 17;

    private const FONT = 'freeserif';

    /** Sirine kolona u milimetrima (isti raspored kao legacy). */
    private const COLUMNS = [15, 15, 20, 85, 15, 20, 20, 15, 30, 35];

    /**
     * @return array{contents: string, rows: int}
     */
    public function render(Library $library, string $locale): array
    {
        $copies = $this->copies($library);

        $pdf = $this->newPdf($locale);

        $rows = $copies->count();
        $pages = $rows > 0 ? $copies->chunk(self::ROWS_PER_PAGE) : collect([collect()]);

        $page = 0;
        foreach ($pages as $chunk) {
            $this->printPage($pdf, $chunk, ++$page, $locale);
        }

        return [
            'contents' => $pdf->Output('', 'S'),
            'rows' => $rows,
        ];
    }

    /**
     * Aktivne kopije biblioteke, hronoloski po inventarnom broju.
     *
     * @return Collection<int, BookCopy>
     */
    private function copies(Library $library): Collection
    {
        return BookCopy::query()
            ->with('book.authors')
            ->where('library_id', $library->id)
            ->orderByRaw('CAST(order_number AS BIGINT) ASC')
            ->orderBy('order_number')
            ->orderBy('id')
            ->get();
    }

    private function newPdf(string $locale): TCPDF
    {
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('Ebiblioteka.rs');
        $pdf->SetAuthor('Ebiblioteka.rs');
        $pdf->SetTitle(trans('inventory.pdf.title', [], $locale));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(self::MARGIN_LEFT, self::MARGIN_TOP, self::MARGIN_RIGHT);
        $pdf->SetAutoPageBreak(false);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont(self::FONT, '', 10);

        return $pdf;
    }

    /**
     * @param  Collection<int, BookCopy>  $rows
     */
    private function printPage(TCPDF $pdf, Collection $rows, int $page, string $locale): void
    {
        $pdf->AddPage();

        $header = [
            trans('inventory.pdf.header.order_number', [], $locale),
            trans('inventory.pdf.header.seq_number', [], $locale),
            trans('inventory.pdf.header.date', [], $locale),
            trans('inventory.pdf.header.description', [], $locale),
            trans('inventory.pdf.header.binding', [], $locale),
            trans('inventory.pdf.header.dimension', [], $locale),
            trans('inventory.pdf.header.origin', [], $locale),
            trans('inventory.pdf.header.price', [], $locale),
            trans('inventory.pdf.header.udk', [], $locale),
            trans('inventory.pdf.header.notice', [], $locale),
        ];

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont(self::FONT, 'B', 10);

        foreach ($header as $index => $title) {
            $pdf->MultiCell(
                self::COLUMNS[$index], self::HEADER_HEIGHT, $title, 1, 'C',
                false, 0, '', '', true, 0, false, true, self::HEADER_HEIGHT, 'M'
            );
        }

        $pdf->SetFont(self::FONT, '', 10);
        $pdf->Ln();
        $pdf->SetFillColor(224, 235, 255);
        $pdf->SetTextColor(0);

        foreach ($rows as $copy) {
            $cells = [
                [self::COLUMNS[0], $copy->order_number, 'C'],
                [self::COLUMNS[1], $copy->seq_number, 'C'],
                [self::COLUMNS[2], $copy->date_add?->format('d.m.Y'), 'C'],
                [self::COLUMNS[3], $this->mainArea($copy), 'L'],
                [self::COLUMNS[4], $copy->binding, 'C'],
                [self::COLUMNS[5], $copy->dimension, 'C'],
                [self::COLUMNS[6], $copy->origin, 'C'],
                [self::COLUMNS[7], $copy->price, 'R'],
                [self::COLUMNS[8], $copy->udk, 'C'],
                [self::COLUMNS[9], $copy->notice, 'L'],
            ];

            foreach ($cells as [$width, $value, $align]) {
                $pdf->MultiCell(
                    $width, self::ROW_HEIGHT, (string) $value, 1, $align,
                    false, 0, '', '', true, 0, false, true, self::ROW_HEIGHT, 'M'
                );
            }

            $pdf->Ln();
        }

        $padding = self::HEADER_HEIGHT - strlen((string) $page);
        $pdf->Text(270, 195, str_repeat(' ', max(0, $padding)).$page);
    }

    /**
     * Autor + naslov + izdavacki blok, format identican legacy izvestaju.
     */
    private function mainArea(BookCopy $copy): string
    {
        $author = $copy->book?->authors->first()?->displayName() ?? '';
        $name = $copy->book?->name ?? '';

        $publication = trim(
            ($copy->publish_place !== null ? $copy->publish_place.': ' : '')
            .($copy->publisher ?? '')
            .' '.($copy->publish_year ?? '')
        );

        $main = $author."\r\n    ".$name;

        if ($publication !== '') {
            $main .= ' - '.$publication;
        }

        return $main;
    }
}
