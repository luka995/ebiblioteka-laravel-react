<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Razred odeljenja (1-8).
 *
 * U bazi se cuva kao integer, a u UI-u se prikazuje i rimskim brojem.
 */
enum SchoolGrade: int
{
    use EnumToArray;

    case First = 1;
    case Second = 2;
    case Third = 3;
    case Fourth = 4;
    case Fifth = 5;
    case Sixth = 6;
    case Seventh = 7;
    case Eighth = 8;

    public function getLabel(): string
    {
        return match ($this) {
            self::First => 'I',
            self::Second => 'II',
            self::Third => 'III',
            self::Fourth => 'IV',
            self::Fifth => 'V',
            self::Sixth => 'VI',
            self::Seventh => 'VII',
            self::Eighth => 'VIII',
        };
    }

    /**
     * Labela za select opcije, npr. "3 (III)".
     */
    public function getFullLabel(): string
    {
        return $this->value.' ('.$this->getLabel().')';
    }
}
