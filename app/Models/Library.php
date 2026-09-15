<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\LibraryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'address', 'place_id', 'work_time', 'inv_number_auto', 'deleted'])]
class Library extends Model
{
    /** @use HasFactory<LibraryFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'inv_number_auto' => true,
    ];

    protected function casts(): array
    {
        return [
            'inv_number_auto' => 'boolean',
            'deleted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Svaka biblioteka dobija svoju sekvencu inventarnih brojeva.
        static::created(function (Library $library): void {
            $library->inventorySequence()->firstOrCreate([], ['last_number' => 0]);
        });
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Tagovi kreirani na nivou ove biblioteke.
     *
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Korisnici sa aktivnim članstvom u biblioteci (many-to-many).
     *
     * Isključuje deaktivirana članstva (soft-deleted pivot redove).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(LibraryUserPivot::class)
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * Svi korisnici uključujući deaktivirana članstva.
     *
     * @return BelongsToMany<User, $this>
     */
    public function usersWithTrashed(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(LibraryUserPivot::class)
            ->withTimestamps()
            ->withPivot('deleted_at');
    }

    /**
     * Naslovi (books) ove biblioteke.
     *
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * Fizicke jedinice ove biblioteke (ukljucujuci arhivirane).
     *
     * @return HasMany<BookCopy, $this>
     */
    public function bookCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class)->withTrashed();
    }

    /**
     * Per-library sekvenca inventarnih brojeva.
     *
     * @return HasOne<BookInventorySequence, $this>
     */
    public function inventorySequence(): HasOne
    {
        return $this->hasOne(BookInventorySequence::class);
    }

    /**
     * Otpisi fizickih jedinica ove biblioteke.
     *
     * @return HasMany<BookCopyWriteOff, $this>
     */
    public function bookCopyWriteOffs(): HasMany
    {
        return $this->hasMany(BookCopyWriteOff::class);
    }
}
