<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'library_id',
    'user_id',
    'book_id',
    'book_copy_id',
    'from_date',
    'valid_till',
    'status',
    'ended_at',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'from_date' => 'datetime',
            'valid_till' => 'datetime',
            'status' => ReservationStatus::class,
            'ended_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withTrashed();
    }

    /**
     * @return BelongsTo<BookCopy, $this>
     */
    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class)->withTrashed();
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ReservationStatus::Active);
    }

    /**
     * Aktivna rezervacija kojoj rok nije istekao.
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', now());
    }

    public function isActive(): bool
    {
        return $this->status === ReservationStatus::Active;
    }

    public function isExpired(): bool
    {
        return $this->status === ReservationStatus::Active && $this->valid_till->isPast();
    }

    /**
     * Pozajmica kojom je rezervacija realizovana (ako postoji).
     *
     * @return HasOne<Borrow, $this>
     */
    public function borrow(): HasOne
    {
        return $this->hasOne(Borrow::class);
    }
}
