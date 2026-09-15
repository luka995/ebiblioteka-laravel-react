<?php

namespace App\Models;

use Database\Factories\BookCopyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'library_id',
    'book_id',
    'order_number',
    'seq_number',
    'barcode',
    'isbn',
    'publisher',
    'publish_place',
    'publish_year',
    'issue_number',
    'num_of_pages',
    'dimension',
    'part',
    'udk',
    'binding',
    'origin',
    'book_number',
    'place_on_shelf',
    'price',
    'date_add',
    'notice',
    'borrowed',
    'reserved',
    'rec_error',
    'rec_error_notice',
])]
class BookCopy extends Model
{
    /** @use HasFactory<BookCopyFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'date_add' => 'date',
            'borrowed' => 'boolean',
            'reserved' => 'boolean',
            'rec_error' => 'boolean',
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
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withTrashed();
    }

    /**
     * Svi otpisi (istorija, append-only).
     *
     * @return HasMany<BookCopyWriteOff, $this>
     */
    public function writeOffs(): HasMany
    {
        return $this->hasMany(BookCopyWriteOff::class);
    }

    /**
     * Aktivan otpis (ako postoji) — otpisano i nije ponisteno.
     *
     * @return HasOne<BookCopyWriteOff, $this>
     */
    public function activeWriteOff(): HasOne
    {
        return $this->hasOne(BookCopyWriteOff::class)->whereNull('cancelled_at');
    }

    public function isWrittenOff(): bool
    {
        return $this->writeOffs()->whereNull('cancelled_at')->exists();
    }
}
