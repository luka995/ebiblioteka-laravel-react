<?php

namespace App\Services;

use App\Exceptions\InventoryNumberCollisionException;
use App\Models\BookCopy;
use App\Models\BookInventorySequence;
use App\Models\Library;
use App\Support\BarCode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dodela i odrzavanje inventarnih brojeva fizickih jedinica po biblioteci.
 *
 * Auto rezim koristi per-library sekvencu (`book_inventory_seq`) uz
 * `lockForUpdate` u transakciji. Barkod se uvek izvodi iz inventarnog broja
 * (dopuna nulama do 12 cifara + EAN-13 kontrolna cifra).
 */
class InventoryNumberService
{
    private const MAX_DIGITS = 12;

    /**
     * Sledeci slobodan inventarni broj; atomski inkrementuje sekvencu.
     */
    public function next(Library $library): string
    {
        return DB::transaction(function () use ($library): string {
            $sequence = $this->lockSequence($library);
            $candidate = (int) $sequence->last_number + 1;

            if ($this->orderNumberExists($library, $candidate)) {
                throw new InventoryNumberCollisionException((string) $candidate);
            }

            $sequence->last_number = $candidate;
            $sequence->save();

            return (string) $candidate;
        });
    }

    /**
     * Sledeca vrednost bez inkrementa (za prikaz/proveru).
     */
    public function nextValue(Library $library): int
    {
        return (int) $this->sequence($library)->last_number + 1;
    }

    /**
     * Poravnava sekvencu na najveci postojeci (ne-arhivirani) broj.
     */
    public function syncToMaxExisting(Library $library): int
    {
        return DB::transaction(function () use ($library): int {
            $max = $this->maxExisting($library);
            $sequence = $this->lockSequence($library);
            $sequence->last_number = $max;
            $sequence->save();

            return $max;
        });
    }

    /**
     * Evidentira rucno unet broj: sekvenca nikad ne ide unazad.
     */
    public function recordManual(Library $library, string $orderNumber): void
    {
        DB::transaction(function () use ($library, $orderNumber): void {
            $value = (int) $this->normalizeOrderNumber($orderNumber);
            $sequence = $this->lockSequence($library);

            if ($value > (int) $sequence->last_number) {
                $sequence->last_number = $value;
                $sequence->save();
            }
        });
    }

    /**
     * Najveci broj medju postojećim (ne-arhiviranim) kopijama.
     */
    public function maxExisting(Library $library): int
    {
        return $this->maxOrderNumber($library, withTrashed: false);
    }

    /**
     * Najveci broj medju svim kopijama, ukljucujuci arhivirane.
     */
    public function maxUsed(Library $library): int
    {
        return $this->maxOrderNumber($library, withTrashed: true);
    }

    public function orderNumberExists(Library $library, int|string $orderNumber, ?BookCopy $ignore = null): bool
    {
        $query = BookCopy::withTrashed()
            ->where('library_id', $library->id)
            ->whereNotNull('order_number')
            ->whereRaw('CAST(order_number AS BIGINT) = ?', [(int) $orderNumber]);

        if ($ignore !== null) {
            $query->whereKeyNot($ignore->getKey());
        }

        return $query->exists();
    }

    /**
     * Normalizuje i validira inventarni broj; baca izuzetak za neispravan unos.
     */
    public function normalizeOrderNumber(string|int $orderNumber): string
    {
        $value = trim((string) $orderNumber);

        if ($value === '' || ! preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException('Inventory number must contain only digits.');
        }

        return (string) (int) $value;
    }

    /**
     * EAN-13 barkod izveden iz inventarnog broja.
     */
    public function barcodeFor(string|int $orderNumber): string
    {
        $normalized = $this->normalizeOrderNumber($orderNumber);

        if (strlen($normalized) > self::MAX_DIGITS) {
            throw new InvalidArgumentException('Inventory number cannot exceed 12 digits.');
        }

        return BarCode::generateFromBaseNumber($normalized);
    }

    private function maxOrderNumber(Library $library, bool $withTrashed): int
    {
        $query = DB::table('book_copies')
            ->where('library_id', $library->id)
            ->whereNotNull('order_number');

        if (! $withTrashed) {
            $query->whereNull('deleted_at');
        }

        return (int) ($query->selectRaw('MAX(CAST(order_number AS BIGINT)) as max_number')->value('max_number') ?? 0);
    }

    private function sequence(Library $library): BookInventorySequence
    {
        return BookInventorySequence::firstOrCreate(
            ['library_id' => $library->id],
            ['last_number' => 0],
        );
    }

    private function lockSequence(Library $library): BookInventorySequence
    {
        $this->sequence($library);

        return BookInventorySequence::where('library_id', $library->id)->lockForUpdate()->firstOrFail();
    }
}
