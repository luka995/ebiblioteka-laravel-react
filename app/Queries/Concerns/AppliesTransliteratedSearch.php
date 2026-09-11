<?php

namespace App\Queries\Concerns;

use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dodaje LIKE/ILIKE uslove za svaku varijantu pisma (ćirilica/latinica)
 * unetog pojma, tako da pretraga radi bez obzira na pismo u kome je zapis.
 */
trait AppliesTransliteratedSearch
{
    protected function whereTransliterated(
        Builder $query,
        string $column,
        string $term,
        string $boolean = 'and',
    ): void {
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query->where(function (Builder $group) use ($column, $term, $operator) {
            foreach (Text::searchVariants($term) as $variant) {
                $group->orWhere($column, $operator, "%{$variant}%");
            }
        }, null, null, $boolean);
    }
}
