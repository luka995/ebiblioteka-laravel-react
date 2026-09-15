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
 * Nalepnica moze da nosi naslov iznad bar-koda i dodatne redove (npr. naziv
 * biblioteke) ispod EAN cifara. Kada `BarcodeLabel` nema naslov ni dodatne
 * redove, layout je identican starom (korisnicke nalepnice).
 */
class BarcodePdfService
{
    private const FONT = 'dejavusanscondensed';

    private const LABEL_WIDTH = 62.0;

    private const LABEL_HEIGHT = 29.0;

    private const LABEL_BARCODE_X = 6.0;

    private const LABEL_BARCODE_Y = 2.5;

    private const LABEL_BARCODE_WIDTH = 50.0;

    private const LABEL_BARCODE_HEIGHT = 13.5;

    private const LABEL_CODE_Y = 16.5;

    private const LABEL_TEXT_WIDTH = 58.0;

    private const LABEL_TITLE_Y = 1.0;

    private const LABEL_TITLE_FONT = 7.0;

    private const LABEL_BARCODE_Y_EXTENDED = 4.5;

    private const LABEL_BARCODE_HEIGHT_EXTENDED = 13.0;

    private const LABEL_CODE_Y_EXTENDED = 17.5;

    private const LABEL_CODE_FONT_EXTENDED = 8.0;

    private const LABEL_CAPTION_Y_EXTENDED = 21.5;

    private const LABEL_CAPTION_FONT = 7.0;

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

    private const A4_TEXT_WIDTH = 44.0;

    private const A4_TITLE_Y_OFFSET = 0.6;

    private const A4_TITLE_FONT = 5.5;

    private const A4_BARCODE_Y_OFFSET_EXTENDED = 3.0;

    private const A4_BARCODE_HEIGHT_EXTENDED = 10.0;

    private const A4_CODE_Y_OFFSET_EXTENDED = 13.0;

    private const A4_CODE_FONT_EXTENDED = 6.0;

    private const A4_CAPTION_Y_OFFSET_EXTENDED = 15.9;

    private const A4_CAPTION_FONT = 5.5;

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

            $extended = $this->isExtended($label);
            $barcodeY = $extended ? self::LABEL_BARCODE_Y_EXTENDED : self::LABEL_BARCODE_Y;
            $barcodeHeight = $extended ? self::LABEL_BARCODE_HEIGHT_EXTENDED : self::LABEL_BARCODE_HEIGHT;
            $codeY = $extended ? self::LABEL_CODE_Y_EXTENDED : self::LABEL_CODE_Y;
            $codeFont = $extended ? self::LABEL_CODE_FONT_EXTENDED : 10.0;

            if ($extended && $label->title !== null && $label->title !== '') {
                $this->drawCenteredText($pdf, $label->title, self::LABEL_WIDTH / 2, self::LABEL_TITLE_Y, self::LABEL_TEXT_WIDTH, self::LABEL_TITLE_FONT);
            }

            $this->drawBarcode(
                $pdf,
                $label->code,
                self::LABEL_BARCODE_X,
                $barcodeY,
                self::LABEL_BARCODE_WIDTH,
                $barcodeHeight,
            );
            $this->drawCode($pdf, $label->code, self::LABEL_WIDTH / 2, $codeY, $codeFont, self::LABEL_BARCODE_WIDTH);

            foreach ($label->captions as $index => $caption) {
                $this->drawCenteredText($pdf, $caption, self::LABEL_WIDTH / 2, self::LABEL_CAPTION_Y_EXTENDED + $index * 3.4, self::LABEL_TEXT_WIDTH, self::LABEL_CAPTION_FONT);
            }
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
                $cellX = self::A4_BASE_X + $column * self::A4_CELL_WIDTH;
                $cellY = self::A4_BASE_Y + $row * self::A4_CELL_HEIGHT;

                $extended = $this->isExtended($label);
                $barcodeYOffset = $extended ? self::A4_BARCODE_Y_OFFSET_EXTENDED : self::A4_BARCODE_Y_OFFSET;
                $barcodeHeight = $extended ? self::A4_BARCODE_HEIGHT_EXTENDED : self::A4_BARCODE_HEIGHT;
                $codeYOffset = $extended ? self::A4_CODE_Y_OFFSET_EXTENDED : self::A4_CODE_Y_OFFSET;
                $codeFont = $extended ? self::A4_CODE_FONT_EXTENDED : 10.0;

                if ($extended && $label->title !== null && $label->title !== '') {
                    $this->drawCenteredText($pdf, $label->title, $cellX + self::A4_CELL_WIDTH / 2, $cellY + self::A4_TITLE_Y_OFFSET, self::A4_TEXT_WIDTH, self::A4_TITLE_FONT);
                }

                $this->drawBarcode(
                    $pdf,
                    $label->code,
                    $cellX + self::A4_BARCODE_MARGIN_H,
                    $cellY + $barcodeYOffset,
                    self::A4_CELL_WIDTH - 2 * self::A4_BARCODE_MARGIN_H,
                    $barcodeHeight,
                );
                $this->drawCode(
                    $pdf,
                    $label->code,
                    $cellX + self::A4_CELL_WIDTH / 2,
                    $cellY + $codeYOffset,
                    $codeFont,
                    self::A4_TEXT_WIDTH,
                );

                foreach ($label->captions as $captionIndex => $caption) {
                    $this->drawCenteredText($pdf, $caption, $cellX + self::A4_CELL_WIDTH / 2, $cellY + self::A4_CAPTION_Y_OFFSET_EXTENDED + $captionIndex * 3.0, self::A4_TEXT_WIDTH, self::A4_CAPTION_FONT);
                }
            }
        }

        return $pdf->Output('', 'S');
    }

    private function isExtended(BarcodeLabel $label): bool
    {
        return ($label->title !== null && $label->title !== '') || $label->captions !== [];
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
        $pdf->SetFont(self::FONT, '', 10);

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

    private function drawCode(TCPDF $pdf, string $barcode, float $centerX, float $y, float $fontSize, float $width): void
    {
        $pdf->SetFont(self::FONT, '', $fontSize);
        $pdf->SetXY($centerX - $width / 2, $y);
        $pdf->Cell($width, 5, $barcode, 0, 0, 'C');
    }

    private function drawCenteredText(TCPDF $pdf, string $text, float $centerX, float $y, float $maxWidth, float $fontSize): void
    {
        $text = $this->fitText($pdf, $text, $maxWidth, $fontSize);

        if ($text === '') {
            return;
        }

        $pdf->SetFont(self::FONT, '', $fontSize);
        $pdf->SetXY($centerX - $maxWidth / 2, $y);
        $pdf->Cell($maxWidth, $fontSize * 0.6, $text, 0, 0, 'C');
    }

    /**
     * Skracuje tekst (dodaje `…`) da ne predje zadatu sirinu nalepnice.
     */
    private function fitText(TCPDF $pdf, string $text, float $maxWidth, float $fontSize): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $pdf->SetFont(self::FONT, '', $fontSize);

        if ($pdf->GetStringWidth($text) <= $maxWidth) {
            return $text;
        }

        $available = $maxWidth - $pdf->GetStringWidth('…');
        $result = '';

        for ($i = 0, $length = mb_strlen($text, 'UTF-8'); $i < $length; $i++) {
            $candidate = $result.mb_substr($text, $i, 1, 'UTF-8');

            if ($pdf->GetStringWidth($candidate) > $available) {
                break;
            }

            $result = $candidate;
        }

        return rtrim($result).'…';
    }
}
