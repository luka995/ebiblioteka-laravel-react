<?php

namespace App\Support;

use InvalidArgumentException;

final class BarCodeImage
{
    private const MODULES = 95;

    /**
     * Renders the bars of an EAN-13 code as a resolution-independent SVG.
     *
     * The human-readable value is intentionally left outside the SVG so that
     * the same output can be composed with HTML or a PDF renderer later.
     */
    public static function render(
        string $ean13,
        int $moduleWidth = 2,
        int $height = 60,
        string $color = '#000000',
    ): string {
        if (! BarCode::validate($ean13)) {
            throw new InvalidArgumentException('A valid EAN-13 barcode is required.');
        }

        if ($moduleWidth < 1 || $height < 1) {
            throw new InvalidArgumentException('Barcode dimensions must be positive integers.');
        }

        $sequence = self::toBitSequence($ean13);
        $width = self::MODULES * $moduleWidth;
        $bars = [];

        foreach (self::runs($sequence) as $run) {
            if ($run['bar']) {
                $bars[] = sprintf(
                    '<rect x="%d" y="0" width="%d" height="%d" />',
                    $run['start'] * $moduleWidth,
                    $run['width'] * $moduleWidth,
                    $height,
                );
            }
        }

        $safeColor = htmlspecialchars($color, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img">'.
            '<desc>EAN-13 barcode %s</desc><g fill="%s" shape-rendering="crispEdges">%s</g></svg>',
            $width,
            $height,
            $width,
            $height,
            $ean13,
            $safeColor,
            implode('', $bars),
        );
    }

    /**
     * Converts the 13 digits into the standard 95-module EAN-13 sequence.
     */
    private static function toBitSequence(string $ean13): string
    {
        $leftOdd = [
            '0' => '0001101', '1' => '0011001', '2' => '0010011',
            '3' => '0111101', '4' => '0100011', '5' => '0110001',
            '6' => '0101111', '7' => '0111011', '8' => '0110111',
            '9' => '0001011',
        ];
        $leftEven = [
            '0' => '0100111', '1' => '0110011', '2' => '0011011',
            '3' => '0100001', '4' => '0011101', '5' => '0111001',
            '6' => '0000101', '7' => '0010001', '8' => '0001001',
            '9' => '0010111',
        ];
        $right = [
            '0' => '1110010', '1' => '1100110', '2' => '1101100',
            '3' => '1000010', '4' => '1011100', '5' => '1001110',
            '6' => '1010000', '7' => '1000100', '8' => '1001000',
            '9' => '1110100',
        ];
        $parities = [
            '0' => 'AAAAAA', '1' => 'AABABB', '2' => 'AABBAB',
            '3' => 'AABBBA', '4' => 'ABAABB', '5' => 'ABBAAB',
            '6' => 'ABBBAA', '7' => 'ABABAB', '8' => 'ABABBA',
            '9' => 'ABBABA',
        ];

        $sequence = '101';
        $parity = $parities[$ean13[0]];

        for ($index = 1; $index <= 6; $index++) {
            $table = $parity[$index - 1] === 'A' ? $leftOdd : $leftEven;
            $sequence .= $table[$ean13[$index]];
        }

        $sequence .= '01010';

        for ($index = 7; $index <= 12; $index++) {
            $sequence .= $right[$ean13[$index]];
        }

        return $sequence.'101';
    }

    /**
     * Groups adjacent equal modules so the SVG contains fewer rectangles.
     *
     * @return array<int, array{bar: bool, start: int, width: int}>
     */
    private static function runs(string $sequence): array
    {
        $runs = [];
        $start = 0;
        $current = $sequence[0];
        $length = strlen($sequence);

        for ($index = 1; $index <= $length; $index++) {
            if ($index < $length && $sequence[$index] === $current) {
                continue;
            }

            $runs[] = [
                'bar' => $current === '1',
                'start' => $start,
                'width' => $index - $start,
            ];

            if ($index < $length) {
                $start = $index;
                $current = $sequence[$index];
            }
        }

        return $runs;
    }
}
