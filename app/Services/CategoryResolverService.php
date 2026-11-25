<?php

namespace App\Services;

use App\Models\Category;
use App\Support\Text;

/**
 * Pronalazi ili kreira kategoriju po kanonskom nazivu.
 *
 * Naziv mora biti isti u istoj biblioteci i pod istim roditeljem; redosled
 * reci ostaje znacajan, pa „Naučna fantastika“ i „Fantastika naučna“ nisu
 * ista kategorija.
 */
class CategoryResolverService
{
    public function key(string $name): string
    {
        return Text::labelKey($name);
    }

    public function find(int $libraryId, ?int $parentId, string $name): ?Category
    {
        $key = $this->key($name);

        if ($key === '') {
            return null;
        }

        return Category::query()
            ->where('library_id', $libraryId)
            ->when(
                $parentId === null,
                fn ($query) => $query->whereNull('parent_id'),
                fn ($query) => $query->where('parent_id', $parentId),
            )
            ->get()
            ->first(fn (Category $category): bool => $this->key($category->name) === $key);
    }

    /**
     * @param  array{name: string, library_id: int, parent_id?: int|null}  $data
     */
    public function findOrCreate(array $data): Category
    {
        $existing = $this->find(
            (int) $data['library_id'],
            isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            (string) $data['name'],
        );

        if ($existing instanceof Category) {
            return $existing;
        }

        return Category::create($data);
    }
}
