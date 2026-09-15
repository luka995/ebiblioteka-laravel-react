<?php

namespace App\Models;

use App\Enums\BookCopyWriteOffReason;
use Database\Factories\BookCopyWriteOffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'book_copy_id',
    'book_id',
    'order_number',
    'library_id',
    'reason',
    'occurred_at',
    'notice',
    'cancelled_at',
    'created_by_id',
    'cancelled_by_id',
])]
class BookCopyWriteOff extends Model
{
    /** @use HasFactory<BookCopyWriteOffFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reason' => BookCopyWriteOffReason::class,
            'occurred_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BookCopy, $this>
     */
    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withTrashed();
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function isActive(): bool
    {
        return $this->cancelled_at === null;
    }
}
