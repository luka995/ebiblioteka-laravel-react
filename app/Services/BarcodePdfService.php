<?php

namespace App\Services;

use App\Enums\BarcodePrintFormat;
use App\Support\BarCode;
use App\Support\BarcodeLabel;
use InvalidArgumentException;
use TCPDF;

/**
 * Generise PDF nalepnice sa EAN-13 bar-kodovima.
 *
 * Bar-kod se samo ucitava i stampa; nikada se ne regenerise. TCPDF native
 * write1DBarcode() se koristi da bi se izbegla zavisnost od GD ekstenzije
 * (nije instalirana u kontejneru).
 *
 * Isti layout se koristi za pojedinacnu i bulk stampu, a `BarcodeLabel` nosi
 * podatke nalepnice kako bi se kasnije (knjige) dodali dodatni redovi teksta
 * bez promene rasporeda.
 */
class BarcodePdfService
{
    private const LABEL_WIDTH = 62.0;

    private const LABEL_HEIGHT = 29.0;

    private const LABEL_BARCODE_X = 6.0;

    private const LABEL_BARCODE_Y = 2.5;

    private const LABEL_BARCODE_WIDTH = 50.0;

    private const LABEL_BARCODE_HEIGHT = 13.5;

    private const LABEL_CODE_Y = 16.5;

    private const A4_COLUMNS = 4;

    private const A4_ROWS = 12;

    private const A4_PER_PAGE = self::A4_COLUMNS * self::A4_ROWS;

    private const A4_CELL_WIDTH = 48.23;

    private const A4_CELL_HEIGHT = 21.17;

    private const A4_BASE_X = 9.75;

    private const A4_BASE_Y = 12.0;

    private const A4_BARCODE_MARGIN_H = 4.0;

    private const A4_BARCODE_Y_OFFSET = 1.5;

    private const A4_BARCODE_HEIGHT = 13.5;

    private const A4_CODE_Y_OFFSET = 16.0;

    /**
     * @param  array<int, BarcodeLabel>  $labels
     */
    public function render(array $labels, BarcodePrintFormat $format): string
    {
        if ($labels === []) {
            throw new InvalidArgumentException('At least one barcode label is required.');
        }

        foreach ($labels as $label) {
            if (! BarCode::validate($label->code)) {
                throw new InvalidArgumentException('A valid EAN-13 barcode is required.');
            }
        }

        return match ($format) {
            BarcodePrintFormat::Label => $this->renderLabelPages($labels),
            BarcodePrintFormat::A4 => $this->renderSheets($labels),
        };
    }

    /**
     * Jedna strana 62x29mm po bar-kodu.
     *
     * @param  array<int, BarcodeLabel>  $labels
     */
    private function renderLabelPages(array $labels): string
    {
        $pdf = $this->newPdf('L', [self::LABEL_HEIGHT, self::LABEL_WIDTH]);

        foreach ($labels as $label) {
            $pdf->AddPage();

            $this->drawBarcode(
                $pdf,
                $label->code,
                self::LABEL_BARCODE_X,
                self::LABEL_BARCODE_Y,
                self::LABEL_BARCODE_WIDTH,
                self::LABEL_BARCODE_HEIGHT,
            );
            $this->drawCode($pdf, $label->code, self::LABEL_WIDTH / 2, self::LABEL_CODE_Y);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * A4 listovi sa 48 nalepnica (4 kolone x 12 redova), row-major.
     *
     * @param  array<int, BarcodeLabel>  $labels
     */
    private function renderSheets(array $labels): string
    {
        $pdf = $this->newPdf('P', 'A4');

        foreach (array_chunk($labels, self::A4_PER_PAGE) as $page) {
            $pdf->AddPage();

            foreach ($page as $index => $label) {
                $column = $index % self::A4_COLUMNS;
                $row = intdiv($index, self::A4_COLUMNS);

                $this->drawBarcode(
                    $pdf,
                    $label->code,
                    self::A4_BASE_X + self::A4_BARCODE_MARGIN_H + $column * self::A4_CELL_WIDTH,
                    self::A4_BASE_Y + self::A4_BARCODE_Y_OFFSET + $row * self::A4_CELL_HEIGHT,
                    self::A4_CELL_WIDTH - 2 * self::A4_BARCODE_MARGIN_H,
                    self::A4_BARCODE_HEIGHT,
                );
                $this->drawCode(
                    $pdf,
                    $label->code,
                    self::A4_BASE_X + self::A4_CELL_WIDTH / 2 + $column * self::A4_CELL_WIDTH,
                    self::A4_BASE_Y + self::A4_CODE_Y_OFFSET + $row * self::A4_CELL_HEIGHT,
                );
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * @param  string|array<int, float>  $format
     */
    private function newPdf(string $orientation, string|array $format): TCPDF
    {
        $pdf = new TCPDF($orientation, 'mm', $format, true, 'UTF-8', false);
        $pdf->SetCreator('eBiblioteka');
        $pdf->SetAuthor('Biblioteka');
        $pdf->SetTitle('Bar-kod');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('helvetica', '', 10);

        return $pdf;
    }

    private function drawBarcode(TCPDF $pdf, string $barcode, float $x, float $y, float $width, float $height): void
    {
        // stretch bez fitwidth i bez paddinga: crte pune tacno zadatu
        // sirinu x visinu (fitwidth bi iskljucio stretch, a 'auto' padding
        // je skrivao deo visine).
        $style = [
            'position' => '',
            'align' => 'C',
            'stretch' => true,
            'fitwidth' => false,
            'border' => false,
            'hpadding' => 0,
            'vpadding' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'text' => false,
        ];

        $pdf->write1DBarcode($barcode, 'EAN13', $x, $y, $width, $height, 0.4, $style, 'N');
    }

    private function drawCode(TCPDF $pdf, string $barcode, float $centerX, float $y): void
    {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY($centerX - 25, $y);
        $pdf->Cell(50, 5, $barcode, 0, 0, 'C');
    }
}
