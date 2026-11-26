<?php

namespace App\Services\BookImports;

use App\DTOs\BookImportHeaderMapDTO;
use App\DTOs\BookImportRowDataDTO;
use App\Enums\BookImportFailureEnum;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;

class BookImportRowPayloadMapper
{
    /**
     * @param  array<int, Cell>  $cells
     */
    public function map(array $cells, int $rowNumber, BookImportHeaderMapDTO $header): ?BookImportRowDataDTO
    {
        $payload = [];
        $invalidField = null;
        $hasData = false;

        foreach ($header->columnMap as $field => $index) {
            $cell = $cells[$index] ?? null;
            $value = $cell?->getValue();
            $hasData = $hasData || ($value !== null && $value !== '');
            $text = $this->text($value);

            if ($cell instanceof FormulaCell || ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value))) {
                $invalidField ??= $field;
                $payload[$field] = $text;

                continue;
            }

            $payload[$field] = $text;

            if ($field === 'authors') {
                $payload[$field] = $text === null ? [] : array_values(array_filter(
                    array_map('trim', preg_split('/[;|]/u', $text)),
                    fn (string $author): bool => $author !== '',
                ));

                foreach ($payload[$field] as $author) {
                    if (mb_strlen($author) > 255) {
                        $invalidField ??= $field;
                    }
                }
            } elseif (in_array($field, ['seq_number', 'num_of_pages'], true) && $text !== null) {
                $min = $field === 'seq_number' ? -2147483648 : 0;
                $number = preg_match('/^[+-]?\d+$/', $text)
                    && (float) $text >= $min && (float) $text <= 2147483647 ? (int) $text : false;

                if ($number === false) {
                    $invalidField ??= $field;
                } else {
                    $payload[$field] = $number;
                }
            } elseif ($field === 'price' && $text !== null) {
                $price = str_replace(' ', '', $text);

                if (str_contains($price, ',') && str_contains($price, '.')) {
                    $decimal = strrpos($price, ',') > strrpos($price, '.') ? ',' : '.';
                    $price = str_replace($decimal === ',' ? '.' : ',', '', $price);
                }

                $price = str_replace(',', '.', $price);
                $payload[$field] = is_numeric($price) ? number_format((float) $price, 2, '.', '') : '0.00';

                if (is_numeric($price) && ((float) $price < 0 || (float) $price > 99999999.99)) {
                    $invalidField ??= $field;
                }
            } elseif ($text !== null) {
                $maxLength = match ($field) {
                    'description', 'notice' => null,
                    'isbn', 'inventory_number' => 32,
                    'publish_year' => 16,
                    default => 255,
                };

                if ($maxLength !== null && mb_strlen($text) > $maxLength) {
                    $invalidField ??= $field;
                }

                if ($field === 'cover_url' && (
                    ! filter_var($text, FILTER_VALIDATE_URL)
                    || ! in_array(strtolower(parse_url($text, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
                )) {
                    $invalidField ??= $field;
                }
            }
        }

        if (! $hasData || ($invalidField === null && collect($payload)->every(fn ($value) => $value === null || $value === []))) {
            return null;
        }

        return new BookImportRowDataDTO(
            $rowNumber,
            $payload,
            $invalidField === null ? null : BookImportFailureEnum::InvalidFieldValue,
            $invalidField,
        );
    }

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
