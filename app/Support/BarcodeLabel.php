<?php

namespace App\Support;

/**
 * Podaci za jednu bar-kod nalepnicu.
 *
 * `title` se ispisuje iznad bar-koda (npr. naziv knjige), a `captions` ispod
 * human-readable EAN cifara (npr. naziv biblioteke). Za korisnicke nalepnice
 * oba polja ostaju prazna i layout je identican starom.
 */
final readonly class BarcodeLabel
{
    /**
     * @param  list<string>  $captions
     */
    public function __construct(
        public string $code,
        public ?string $title = null,
        public array $captions = [],
    ) {}
}
