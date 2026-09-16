<?php

namespace App\Models;

use App\Enums\InventoryBookStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zahtev za generisanje PDF inventarne knjige jedne biblioteke.
 *
 * Fajl se cuva na privatnom `inventory` disku i dostupan je iskljucivo kroz
 * autorizovani download endpoint (nikada javno).
 */
#[Fillable([
    'library_id',
    'user_id',
    'status',
    'file_path',
    'file_size',
    'rows_count',
    'locale',
    'started_at',
    'finished_at',
    'failure_reason',
])]
class InventoryBook extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InventoryBookStatus::class,
            'file_size' => 'integer',
            'rows_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === InventoryBookStatus::Completed;
    }

    public function isInProgress(): bool
    {
        return $this->status instanceof InventoryBookStatus && $this->status->isInProgress();
    }
}
