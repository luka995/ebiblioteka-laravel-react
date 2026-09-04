<?php

namespace App\Queries;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kolonska pretraga korisnika (Laravel ekvivalent Yii2 SearchModel klase).
 *
 * Prima mapu filter parametara i gradi upit po pojedinačnim kolonama,
 * zamenjujući catch-all `search` ILIKE pretragu.
 */
class UserFilters
{
    /**
     * Mapiranje filter parametra -> DB kolona (pretraga ILIKE, case-insensitive).
     *
     * @var array<string, string>
     */
    private const TEXT_FILTERS = [
        'email' => 'email',
        'username' => 'username',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'bar_code' => 'bar_code',
        'jmbg' => 'jmbg',
        'city' => 'city',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        foreach (self::TEXT_FILTERS as $param => $column) {
            $value = trim((string) ($filters[$param] ?? ''));

            if ($value !== '') {
                $query->where($column, $operator, "%{$value}%");
            }
        }

        if (array_key_exists('role', $filters) && $filters['role'] !== '') {
            $role = UserRole::tryFrom((string) $filters['role']);

            if ($role === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('role', $role->value);
            }
        }

        if (! empty($filters['library_id'])) {
            $query->whereHas('libraries', fn (Builder $q) => $q->whereKey((int) $filters['library_id']));
        }

        return $query;
    }
}
