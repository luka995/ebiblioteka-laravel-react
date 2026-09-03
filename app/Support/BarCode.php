<?php

namespace App\Support;

use App\Models\User;

/**
 * Generator bar-koda za korisnike biblioteke.
 */
final class BarCode
{
    private const MIN = 1000000000000;

    private const MAX = 9999999999999;

    public static function generate(): string
    {
        do {
            $code = (string) mt_rand(self::MIN, self::MAX);
        } while (User::where('bar_code', $code)->exists());

        return $code;
    }
}
