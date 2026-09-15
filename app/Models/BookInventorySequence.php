<?php

namespace App\Models;

use Database\Factories\BookInventorySequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['library_id', 'last_number'])]
class BookInventorySequence extends Model
{
    /** @use HasFactory<BookInventorySequenceFactory> */
    use HasFactory;

    protected $table = 'book_inventory_seq';

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }
}
