<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'slug',
    'library_id',
    'category_primary_id',
    'category_secondary_id',
    'description',
    'image',
    'cover_url',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

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
    public function categoryPrimary(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_primary_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function categorySecondary(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_secondary_id');
    }

    /**
     * @return BelongsToMany<Author, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_authors');
    }

    /**
     * Fizicke jedinice ovog naslova (ukljucujuci soft-deleted/arhivirane).
     *
     * @return HasMany<BookCopy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class)
            ->withTrashed()
            ->orderByRaw('CAST(order_number AS BIGINT)')
            ->orderBy('id');
    }

    /**
     * Aktivne (ne-arhivirane) fizicke jedinice.
     *
     * @return HasMany<BookCopy, $this>
     */
    public function activeCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class)
            ->orderByRaw('CAST(order_number AS BIGINT)')
            ->orderBy('id');
    }

    /**
     * Root-relative URL lokalno otpremljene korice (npr. /storage/books/covers/slika.png),
     * ili null ako slika nije otpremljena. Eksterni `cover_url` je odvojeno polje.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? parse_url(Storage::disk('public')->url($this->image), PHP_URL_PATH)
            : null;
    }

    protected function slugScopeColumns(): array
    {
        return ['library_id'];
    }
}
