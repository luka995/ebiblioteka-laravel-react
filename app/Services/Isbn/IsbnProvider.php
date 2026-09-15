<?php

namespace App\Services\Isbn;

interface IsbnProvider
{
    /**
     * Identifikator izvora (npr. `open_library`).
     */
    public function name(): string;

    /**
     * Vraca metapodatke za dati (normalizovani) ISBN ili null ako nema rezultata.
     */
    public function lookup(string $isbn): ?BookMetadata;
}
