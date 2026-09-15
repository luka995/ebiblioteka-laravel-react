<?php

namespace App\Support;

use App\Models\BookCopy;
use Illuminate\Support\Collection;

/**
 * Rezultat provere usklađenosti per-library sekvence inventarnih brojeva.
 *
 * Do discrepancy-ja dolazi kada bi sledeci automatski broj (`nextAuto`) pao
 * ispod ili na vec iskoriscenu vrednost (`maxUsed`, ukljucujuci arhivirane
 * kopije). Tada se kreiranje blokira i trazi resavanje arhive.
 */
final readonly class InventoryDiscrepancy
{
    /**
     * @param  Collection<int, BookCopy>  $archivedCandidates
     */
    public function __construct(
        public int $nextAuto,
        public int $maxExisting,
        public int $maxUsed,
        public Collection $archivedCandidates,
    ) {}
}
