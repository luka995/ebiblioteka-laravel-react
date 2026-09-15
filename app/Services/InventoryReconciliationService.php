<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Library;
use App\Support\InventoryDiscrepancy;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Provera usklađenosti inventarnih brojeva pre kreiranja fizicke jedinice.
 *
 * Pravilo: nema kreiranja kopije ako bi automatski broj pao ispod/poklopio se
 * sa vec iskoriscenim (`nextAuto <= maxUsed`, ukljucujuci arhivu). Tada se
 * kreiranje blokira, a arhiva se resava na ekranu Arhive.
 */
class InventoryReconciliationService
{
    public function __construct(private readonly InventoryNumberService $numbers) {}

    /**
     * Vraca opis discrepancy-ja ili null ako je sve usklađeno.
     */
    public function inspect(Library $library): ?InventoryDiscrepancy
    {
        $nextAuto = $this->numbers->nextValue($library);
        $maxUsed = $this->numbers->maxUsed($library);

        if ($nextAuto > $maxUsed) {
            return null;
        }

        return new InventoryDiscrepancy(
            nextAuto: $nextAuto,
            maxExisting: $this->numbers->maxExisting($library),
            maxUsed: $maxUsed,
            archivedCandidates: $this->archivedCandidates($library, $nextAuto),
        );
    }

    /**
     * Validira rucno unet inventarni broj i vraca normalizovanu vrednost.
     *
     * @throws ValidationException
     */
    public function validateManual(Library $library, string $orderNumber, ?BookCopy $ignore = null): string
    {
        $normalized = $this->numbers->normalizeOrderNumber($orderNumber);

        if (strlen($normalized) > 12) {
            throw ValidationException::withMessages([
                'order_number' => __('validation.custom.book_copy_order_number_too_long'),
            ]);
        }

        if ($this->numbers->orderNumberExists($library, $normalized, $ignore)) {
            throw ValidationException::withMessages([
                'order_number' => __('validation.custom.book_copy_duplicate_order_number'),
            ]);
        }

        if ((int) $normalized > $this->numbers->maxUsed($library) + 1) {
            throw ValidationException::withMessages([
                'order_number' => __('validation.custom.book_copy_order_number_skip'),
            ]);
        }

        return $normalized;
    }

    /**
     * @return Collection<int, BookCopy>
     */
    private function archivedCandidates(Library $library, int $from): Collection
    {
        return BookCopy::onlyTrashed()
            ->where('library_id', $library->id)
            ->whereNotNull('order_number')
            ->whereRaw('CAST(order_number AS BIGINT) >= ?', [$from])
            ->orderByRaw('CAST(order_number AS BIGINT)')
            ->get();
    }
}
