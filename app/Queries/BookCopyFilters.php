<?php

namespace App\Queries;

use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Support\BarCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kolonska pretraga fizickih jedinica (Laravel ekvivalent Yii2 SearchModel klase).
 *
 * Prima mapu filter parametara i gradi upit po pojedinacnim kolonama. Tekstualne
 * kolone se pretrazuju i u cirilici i u latinici, dok se enum/boolean/opsezi
 * prevode u precizne WHERE uslove. Skup `library_id` i aktivna biblioteka
 * ostaju odgovornost kontrolera.
 */
class BookCopyFilters
{
    use AppliesTransliteratedSearch;

    /**
     * Mapiranje filter parametra -> DB kolona (pretraga ILIKE, case-insensitive).
     *
     * @var array<string, string>
     */
    private const TEXT_FILTERS = [
        'publisher' => 'publisher',
        'publish_place' => 'publish_place',
        'publish_year' => 'publish_year',
        'issue_number' => 'issue_number',
        'dimension' => 'dimension',
        'part' => 'part',
        'udk' => 'udk',
        'book_number' => 'book_number',
        'place_on_shelf' => 'place_on_shelf',
        'notice' => 'notice',
        'rec_error_notice' => 'rec_error_notice',
    ];

    /** @var list<string> */
    private const BINDINGS = ['t', 'b', 'k', 'ko', 'l'];

    /** @var list<string> */
    private const ORIGINS = ['ob', 'ku', 'ra', 'po'];

    /** @var list<string> */
    private const STATUSES = ['available', 'borrowed', 'record_error', 'written_off'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        foreach (self::TEXT_FILTERS as $param => $column) {
            $value = trim((string) ($filters[$param] ?? ''));

            if ($value !== '') {
                $this->whereTransliterated($query, $column, $value);
            }
        }

        if (! empty($filters['book_id'])) {
            $query->where('book_id', (int) $filters['book_id']);
        }

        $barcode = BarCode::normalizeSearchInput(trim((string) ($filters['barcode'] ?? '')));

        if ($barcode !== '') {
            $query->where('barcode', 'like', '%'.$barcode.'%');
        }

        $orderNumber = trim((string) ($filters['order_number'] ?? ''));

        if ($orderNumber !== '') {
            $query->where('order_number', 'like', '%'.$orderNumber.'%');
        }

        $isbn = trim((string) ($filters['isbn'] ?? ''));

        if ($isbn !== '') {
            $query->where('isbn', 'like', '%'.$isbn.'%');
        }

        $term = trim((string) ($filters['search'] ?? ''));

        if ($term !== '') {
            $query->whereHas('book', fn (Builder $book) => $this->whereTransliterated($book, 'name', $term));
        }

        $seqNumber = trim((string) ($filters['seq_number'] ?? ''));

        if ($seqNumber !== '') {
            $query->where('seq_number', (int) $seqNumber);
        }

        $this->applyEnum($query, $filters, 'binding', self::BINDINGS);
        $this->applyEnum($query, $filters, 'origin', self::ORIGINS);

        $this->applyRange($query, $filters, 'price_from', 'price_to', 'price');
        $this->applyRange($query, $filters, 'num_of_pages_from', 'num_of_pages_to', 'num_of_pages');
        $this->applyDateRange($query, $filters, 'date_add_from', 'date_add_to', 'date_add');

        $this->applyBoolean($query, $filters, 'borrowed');
        $this->applyBoolean($query, $filters, 'reserved');
        $this->applyBoolean($query, $filters, 'rec_error');

        $this->applyStatus($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $allowed
     */
    private function applyEnum(Builder $query, array $filters, string $param, array $allowed): void
    {
        $value = trim((string) ($filters[$param] ?? ''));

        if ($value === '') {
            return;
        }

        if (! in_array($value, $allowed, true)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($param, $value);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyRange(Builder $query, array $filters, string $fromParam, string $toParam, string $column): void
    {
        $from = $filters[$fromParam] ?? null;

        if ($from !== null && $from !== '' && is_numeric($from)) {
            $query->where($column, '>=', $from);
        }

        $to = $filters[$toParam] ?? null;

        if ($to !== null && $to !== '' && is_numeric($to)) {
            $query->where($column, '<=', $to);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRange(Builder $query, array $filters, string $fromParam, string $toParam, string $column): void
    {
        $from = trim((string) ($filters[$fromParam] ?? ''));

        if ($from !== '') {
            $query->whereDate($column, '>=', $from);
        }

        $to = trim((string) ($filters[$toParam] ?? ''));

        if ($to !== '') {
            $query->whereDate($column, '<=', $to);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyBoolean(Builder $query, array $filters, string $param): void
    {
        if (! array_key_exists($param, $filters)) {
            return;
        }

        $value = $filters[$param];

        if ($value === '' || $value === null) {
            return;
        }

        $query->where($param, filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyStatus(Builder $query, array $filters): void
    {
        $status = trim((string) ($filters['status'] ?? ''));

        if ($status === '') {
            return;
        }

        if (! in_array($status, self::STATUSES, true)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($status === 'available') {
            $query
                ->whereDoesntHave('activeWriteOff')
                ->where('borrowed', false)
                ->where('rec_error', false);

            return;
        }

        if ($status === 'written_off') {
            $query->whereHas('activeWriteOff');

            return;
        }

        $query->where($status === 'borrowed' ? 'borrowed' : 'rec_error', true);
    }
}
