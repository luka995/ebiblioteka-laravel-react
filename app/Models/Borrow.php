<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'library_id',
    'user_id',
    'book_id',
    'book_copy_id',
    'reservation_id',
    'created_by_id',
    'from_date',
    'valid_till',
    'return_date',
    'returned',
    'overdue_days',
    'overdue_amount',
])]
class Borrow extends Model
{
    protected function casts(): array
    {
        return [
            'from_date' => 'datetime',
            'valid_till' => 'datetime',
            'return_date' => 'datetime',
            'returned' => 'boolean',
            'overdue_days' => 'integer',
            'overdue_amount' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
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
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @param  Builder<Borrow>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('returned', false);
    }

    /**
     * @param  Builder<Borrow>  $query
     */
    public function scopeReturned(Builder $query): void
    {
        $query->where('returned', true);
    }

    /**
     * @param  Builder<Borrow>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('returned', false)->where('valid_till', '<', now());
    }

    public function isOverdue(): bool
    {
        return ! $this->returned && $this->valid_till->isPast();
    }

    /**
     * Broj kalendarskih dana prekoracenja (0 ako nije prekoraceno).
     */
    public function calculateOverdueDays(?CarbonInterface $at = null): int
    {
        $reference = $this->returned && $this->return_date !== null
            ? $this->return_date
            : ($at ?? now());

        $validDay = (clone $this->valid_till)->startOfDay();
        $referenceDay = (clone $reference)->startOfDay();

        if ($referenceDay->lessThanOrEqualTo($validDay)) {
            return 0;
        }

        return (int) $validDay->diffInDays($referenceDay);
    }

    public function status(): string
    {
        if ($this->returned) {
            return 'returned';
        }

        return $this->isOverdue() ? 'overdue' : 'active';
    }
}
