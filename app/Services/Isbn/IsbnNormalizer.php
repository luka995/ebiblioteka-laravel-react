<?php

namespace App\Services\Isbn;

/**
 * Normalizacija i validacija ISBN-10/ISBN-13 brojeva.
 */
class IsbnNormalizer
{
    /**
     * Uklanja sve osim cifara i zavrsnog X; vraca velika slova.
     */
    public function normalize(string $isbn): string
    {
        $clean = strtoupper(preg_replace('/[^0-9Xx]/', '', $isbn) ?? '');

        return $clean;
    }

    public function isValid(string $isbn): bool
    {
        $isbn = $this->normalize($isbn);

        return match (strlen($isbn)) {
            10 => $this->isValidIsbn10($isbn),
            13 => $this->isValidIsbn13($isbn),
            default => false,
        };
    }

    /**
     * Vraca sve poznate forme (uneta + konvertovana) radi poklapanja sa bazom.
     *
     * @return array<int, string>
     */
    public function forms(string $isbn): array
    {
        $isbn = $this->normalize($isbn);
        $forms = [];

        if ($isbn !== '' && $this->isValid($isbn)) {
            $forms[] = $isbn;

            $converted = strlen($isbn) === 10 ? $this->isbn10To13($isbn) : $this->isbn13To10($isbn);

            if ($converted !== null) {
                $forms[] = $converted;
            }
        }

        return array_values(array_unique($forms));
    }

    private function isValidIsbn10(string $isbn): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 10; $index++) {
            $value = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
            $sum += (10 - $index) * $value;
        }

        return $sum % 11 === 0;
    }

    private function isValidIsbn13(string $isbn): bool
    {
        if (! preg_match('/^\d{13}$/', $isbn)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 13; $index++) {
            $sum += (int) $isbn[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }

    private function isbn10To13(string $isbn10): ?string
    {
        $base = '978'.substr($isbn10, 0, 9);

        return $base.$this->isbn13Checksum($base);
    }

    private function isbn13To10(string $isbn13): ?string
    {
        if (! str_starts_with($isbn13, '978')) {
            return null;
        }

        $base = substr($isbn13, 3, 9);
        $sum = 0;

        for ($index = 0; $index < 9; $index++) {
            $sum += (10 - $index) * (int) $base[$index];
        }

        $check = (11 - ($sum % 11)) % 11;
        $checkDigit = $check === 10 ? 'X' : (string) $check;

        return $base.$checkDigit;
    }

    private function isbn13Checksum(string $base12): string
    {
        $sum = 0;

        for ($index = 0; $index < 12; $index++) {
            $sum += (int) $base12[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
