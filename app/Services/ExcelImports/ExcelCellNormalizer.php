<?php

namespace App\Services\ExcelImports;

final class ExcelCellNormalizer
{
    public function text(mixed $value): ?string
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $text = trim(preg_replace('/[\s\x{00a0}]+/u', ' ', (string) $value));

        return $text === '' ? null : $text;
    }
}
