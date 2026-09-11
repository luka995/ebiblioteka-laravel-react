<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * EAN-13 generator za korisnike i fizicke jedinice biblioteke.
 */
final class BarCode
{
    private const BASE_LENGTH = 12;

    public static function generate(): string
    {
        return DB::transaction(function (): string {
            do {
                $base = self::nextBaseNumber();
                $code = self::appendChecksum($base);
            } while (User::where('bar_code', $code)->exists());

            return $code;
        });
    }

    /**
     * Generates a barcode from an existing number, such as a book inventory number.
     * Numbers longer than the EAN-13 base are rejected instead of being truncated.
     */
    public static function generateFromBaseNumber(string $baseNumber): string
    {
        $baseNumber = trim($baseNumber);

        if ($baseNumber === '' || ! preg_match('/^\d+$/', $baseNumber)) {
            throw new InvalidArgumentException('Barcode base number must contain only digits.');
        }

        if (strlen($baseNumber) > self::BASE_LENGTH) {
            throw new InvalidArgumentException('Barcode base number cannot exceed 12 digits.');
        }

        return self::appendChecksum(str_pad($baseNumber, self::BASE_LENGTH, '0', STR_PAD_LEFT));
    }

    public static function calculateChecksum(string $base12): int
    {
        if (strlen($base12) !== self::BASE_LENGTH || ! preg_match('/^\d{12}$/', $base12)) {
            throw new InvalidArgumentException('EAN-13 checksum requires exactly 12 digits.');
        }

        $sum = 0;

        foreach (str_split($base12) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    public static function validate(string $ean13): bool
    {
        if (! preg_match('/^\d{13}$/', $ean13)) {
            return false;
        }

        return (int) $ean13[12] === self::calculateChecksum(substr($ean13, 0, self::BASE_LENGTH));
    }

    public static function normalizeSearchInput(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{12}$/', $value) === 1 ? '0'.$value : $value;
    }

    private static function appendChecksum(string $base12): string
    {
        return $base12.(string) self::calculateChecksum($base12);
    }

    private static function nextBaseNumber(): string
    {
        // PostgreSQL SEQUENCE je validna alternativa, ali legacy bar_code_seq
        // pojednostavljuje kasniju migraciju podataka. Transakcija i zakljucavanje
        // reda ispod obezbedjuju atomsku dodelu vrednosti.
        $sequence = DB::table('bar_code_seq')->lockForUpdate()->first();

        if ($sequence === null) {
            throw new RuntimeException('Barcode sequence is not initialized.');
        }

        $next = (string) ((int) $sequence->code + 1);

        if (strlen($next) > self::BASE_LENGTH) {
            throw new RuntimeException('Barcode sequence exceeded the 12-digit EAN-13 base.');
        }

        DB::table('bar_code_seq')->update(['code' => $next]);

        return str_pad($next, self::BASE_LENGTH, '0', STR_PAD_LEFT);
    }
}
