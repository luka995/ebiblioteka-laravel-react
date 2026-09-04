<?php

namespace App\Models;

use Database\Factories\LibraryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address', 'place_id', 'work_time', 'deleted'])]
class Library extends Model
{
    /** @use HasFactory<LibraryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'deleted' => 'boolean',
        ];
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
}
