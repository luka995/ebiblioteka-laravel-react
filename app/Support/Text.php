<?php

namespace App\Support;

class Text
{
    private static array $latinToCyrillic = [
        'Lj' => 'Љ', 'LJ' => 'Љ', 'lj' => 'љ',
        'Nj' => 'Њ', 'NJ' => 'Њ', 'nj' => 'њ',
        'Dž' => 'Џ', 'DŽ' => 'Џ', 'dž' => 'џ',
        'A' => 'А', 'a' => 'а',
        'B' => 'Б', 'b' => 'б',
        'V' => 'В', 'v' => 'в',
        'G' => 'Г', 'g' => 'г',
        'D' => 'Д', 'd' => 'д',
        'Đ' => 'Ђ', 'đ' => 'ђ',
        'E' => 'Е', 'e' => 'е',
        'Ž' => 'Ж', 'ž' => 'ж',
        'Z' => 'З', 'z' => 'з',
        'I' => 'И', 'i' => 'и',
        'J' => 'Ј', 'j' => 'ј',
        'K' => 'К', 'k' => 'к',
        'L' => 'Л', 'l' => 'л',
        'M' => 'М', 'm' => 'м',
        'N' => 'Н', 'n' => 'н',
        'O' => 'О', 'o' => 'о',
        'P' => 'П', 'p' => 'п',
        'R' => 'Р', 'r' => 'р',
        'S' => 'С', 's' => 'с',
        'T' => 'Т', 't' => 'т',
        'Ć' => 'Ћ', 'ć' => 'ћ',
        'U' => 'У', 'u' => 'у',
        'F' => 'Ф', 'f' => 'ф',
        'H' => 'Х', 'h' => 'х',
        'C' => 'Ц', 'c' => 'ц',
        'Č' => 'Ч', 'č' => 'ч',
        'Š' => 'Ш', 'š' => 'ш',
    ];

    /**
     * Transliterates Serbian Latin text to Serbian Cyrillic.
     * Used only by the reference (Tema 2) views to keep theme data in Latin.
     */
    public static function cyr(?string $text): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }

        return strtr($text, self::$latinToCyrillic);
    }
}
