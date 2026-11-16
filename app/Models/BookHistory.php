<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'library_id',
    'book_id',
    'book_copy_id',
    'user_id',
    'actor_id',
    'action',
    'action_date',
    'meta',
])]
class BookHistory extends Model
{
    protected $table = 'book_history';

    public const ACTION_BORROW = 'borrow';

    public const ACTION_RETURN = 'return';

    public const ACTION_RESERVATION = 'reservation';

    public const ACTION_RESERVATION_CANCEL = 'reservation_cancel';

    public const ACTION_RESERVATION_EXPIRED = 'reservation_expired';

    public const ACTION_RESERVATION_FULFILLED = 'reservation_fulfilled';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'action_date' => 'datetime',
        ];
    }

    protected function meta(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?array => $value === null
                ? null
                : json_decode((string) $value, true),
            set: fn (mixed $value): ?string => $value === null
                ? null
                : json_encode($value, JSON_UNESCAPED_UNICODE),
        );
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
     * @return BelongsTo<BookCopy, $this>
     */
    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class)->withTrashed();
    }

    /**
     * Korisnik na koga se dogadjaj odnosi (clan).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Korisnik koji je izvrsio akciju (osoblje, vlasnik, sistem).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
