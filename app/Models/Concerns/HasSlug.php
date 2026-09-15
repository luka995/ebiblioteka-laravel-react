<?php

namespace App\Models\Concerns;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generise jedinstven `slug` iz izvorne kolone (podrazumevano `name`).
 *
 * Skopiranje jedinstvenosti se definise preko `slugScopeColumns()` (npr.
 * `library_id` kod kategorija i naslova). Postojeci slug se cuva osim ako se
 * izvorna kolona promeni.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            $source = $model->slugSource();

            if ($source === null || $source === '') {
                return;
            }

            if ($model->slug !== null && ! $model->isDirty($model->slugSourceColumn())) {
                return;
            }

            $model->slug = $model->makeUniqueSlug($source);
        });
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    /**
     * @return array<int, string>
     */
    protected function slugScopeColumns(): array
    {
        return [];
    }

    protected function slugSource(): ?string
    {
        $value = $this->{$this->slugSourceColumn()} ?? null;

        return $value === null ? null : (string) $value;
    }

    protected function makeUniqueSlug(string $source): string
    {
        $base = Str::slug(Text::lat($source)) ?: 'stavka';
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        foreach ($this->slugScopeColumns() as $column) {
            $query->where($column, $this->{$column});
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
    }
}
