<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Formati za stampu korisnickih bar-kod nalepnica.
 *
 * - Label: jedan EAN-13 bar-kod na nalepnici 62x29mm (bar-kod stampac). Cesto
 *   se korisniku salje kao PDF/slika kao zamena za clansku karticu.
 * - A4: A4 list sa 48 nalepnica; za pojedinacnog korisnika stampa se samo prva
 *   nalepnica, a pun list ima smisla pri bulk stampi.
 */
enum BarcodePrintFormat: string
{
    use EnumToArray;

    case Label = 'label';
    case A4 = 'a4';

    public function getLabel(): string
    {
        return match ($this) {
            self::Label => __('barcode.formats.label'),
            self::A4 => __('barcode.formats.a4'),
        };
    }
}
