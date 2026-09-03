<?php

namespace App\Enums\Traits;

/**
 * Trait EnumToArray
 *
 * Provides utility methods for PHP Enums to retrieve names, values and labels.
 * Pattern preuzet iz projekta "portfolio" (app/traits/EnumToArray).
 */
trait EnumToArray
{
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    abstract public function getLabel(): string;

    /**
     * @return array<string, string> [case->name => getLabel()]
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->name] = $case->getLabel();
        }

        return $result;
    }

    /**
     * @return array<string, string> [case->value => getLabel()]
     */
    public static function toArrayWithValue(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->getLabel();
        }

        return $result;
    }
}
