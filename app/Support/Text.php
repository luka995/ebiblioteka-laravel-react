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

    private static array $cyrillicToLatin = [
        'Љ' => 'Lj', 'љ' => 'lj',
        'Њ' => 'Nj', 'њ' => 'nj',
        'Џ' => 'Dž', 'џ' => 'dž',
        'А' => 'A', 'а' => 'a',
        'Б' => 'B', 'б' => 'b',
        'В' => 'V', 'в' => 'v',
        'Г' => 'G', 'г' => 'g',
        'Д' => 'D', 'д' => 'd',
        'Ђ' => 'Đ', 'ђ' => 'đ',
        'Е' => 'E', 'е' => 'e',
        'Ж' => 'Ž', 'ж' => 'ž',
        'З' => 'Z', 'з' => 'z',
        'И' => 'I', 'и' => 'i',
        'Ј' => 'J', 'ј' => 'j',
        'К' => 'K', 'к' => 'k',
        'Л' => 'L', 'л' => 'l',
        'М' => 'M', 'м' => 'm',
        'Н' => 'N', 'н' => 'n',
        'О' => 'O', 'о' => 'o',
        'П' => 'P', 'п' => 'p',
        'Р' => 'R', 'р' => 'r',
        'С' => 'S', 'с' => 's',
        'Т' => 'T', 'т' => 't',
        'Ћ' => 'Ć', 'ћ' => 'ć',
        'У' => 'U', 'у' => 'u',
        'Ф' => 'F', 'ф' => 'f',
        'Х' => 'H', 'х' => 'h',
        'Ц' => 'C', 'ц' => 'c',
        'Ч' => 'Č', 'ч' => 'č',
        'Ш' => 'Š', 'ш' => 'š',
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

    /**
     * Transliterates Serbian Cyrillic text to Serbian Latin.
     * Latin input is returned unchanged.
     */
    public static function lat(?string $text): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }

        return strtr($text, self::$cyrillicToLatin);
    }

    /**
     * Variants of a search term used to match both scripts regardless of the
     * script the record was stored in. Duplicate variants are collapsed.
     *
     * @return array<int, string>
     */
    public static function searchVariants(string $term): array
    {
        if ($term === '') {
            return [];
        }

        return array_values(array_unique([$term, self::cyr($term), self::lat($term)]));
    }
}
