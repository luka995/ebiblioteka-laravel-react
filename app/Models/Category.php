<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'library_id', 'parent_id'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSlug;

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Puna putanja kroz roditelje: „Roditelj / Roditelj / Ime“.
     */
    public function fullName(): string
    {
        $name = $this->name;

        if ($this->parent_id && $this->parent) {
            $name = $this->parent->fullName().' / '.$name;
        }

        return $name;
    }

    /**
     * Da li data kategorija (id) postoji u lancu predaka ove kategorije.
     */
    public function hasAncestor(int $id): bool
    {
        $current = $this->parent;

        while ($current) {
            if ($current->id === $id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    /**
     * Naslovi kojima je ova kategorija primarna.
     *
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'category_primary_id');
    }

    /**
     * @return array<int, string>
     */
    protected function slugScopeColumns(): array
    {
        return ['library_id'];
    }
}
