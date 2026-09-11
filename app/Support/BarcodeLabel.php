<?php

namespace App\Support;

/**
 * Podaci za jednu bar-kod nalepnicu.
 *
 * `captions` je rezervisan za buduce nalepnice (npr. naziv ustanove i naziv
 * knjige) i za korisnike ostaje prazan.
 */
final readonly class BarcodeLabel
{
    /**
     * @param  list<string>  $captions
     */
    public function __construct(
        public string $code,
        public array $captions = [],
    ) {}
}
