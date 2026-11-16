<?php

namespace App\Queries;

use App\Enums\LibraryRole;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Support\BarCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kolonska pretraga korisnika (Laravel ekvivalent Yii2 SearchModel klase).
 *
 * Prima mapu filter parametara i gradi upit po pojedinačnim kolonama,
 * zamenjujući catch-all `search` ILIKE pretragu. Tekstualne kolone se
 * pretražuju i u ćirilici i u latinici.
 */
class UserFilters
{
    use AppliesTransliteratedSearch;

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
        foreach (self::TEXT_FILTERS as $param => $column) {
            $value = trim((string) ($filters[$param] ?? ''));

            if ($param === 'bar_code') {
                $value = BarCode::normalizeSearchInput($value);
            }

            if ($value !== '') {
                $this->whereTransliterated($query, $column, $value);
            }
        }

        if (array_key_exists('role', $filters) && $filters['role'] !== '') {
            $role = LibraryRole::tryFrom((string) $filters['role']);

            if ($role === null) {
                $query->whereRaw('1 = 0');
            } else {
                $roleLibraryId = ! empty($filters['role_library_id'])
                    ? (int) $filters['role_library_id']
                    : (! empty($filters['library_id']) ? (int) $filters['library_id'] : null);

                $query->whereHas('libraries', function (Builder $membership) use ($role, $roleLibraryId): void {
                    $membership->where('library_user.role', $role->value);

                    if ($roleLibraryId !== null) {
                        $membership->whereKey($roleLibraryId);
                    }
                });
            }
        }

        if (! empty($filters['library_id'])) {
            $query->whereHas('libraries', fn (Builder $q) => $q->whereKey((int) $filters['library_id']));
        }

        return $query;
    }
}
