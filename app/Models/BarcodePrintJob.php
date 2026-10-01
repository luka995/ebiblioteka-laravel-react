<?php

namespace App\Models;

use App\Enums\BarcodePrintFormat;
use App\Enums\BarcodePrintJobStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zahtev za generisanje PDF-a sa bar-kodovima jedinica biblioteke.
 *
 * Fajl se cuva na privatnom `barcode` disku i dostupan je iskljucivo kroz
 * autorizovani download endpoint (nikada javno).
 */
#[Fillable([
    'library_id',
    'user_id',
    'scope',
    'format',
    'status',
    'file_path',
    'file_size',
    'items_count',
    'invalid_count',
    'failure_reason',
    'started_at',
    'finished_at',
])]
class BarcodePrintJob extends Model
{
    /** Brza stampa svih jedinica aktivne biblioteke. */
    public const SCOPE_LIBRARY = 'library';

    protected function casts(): array
    {
        return [
            'status' => BarcodePrintJobStatus::class,
            'format' => BarcodePrintFormat::class,
            'file_size' => 'integer',
            'items_count' => 'integer',
            'invalid_count' => 'integer',
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
        return $this->status === BarcodePrintJobStatus::Completed;
    }

    public function isInProgress(): bool
    {
        return $this->status instanceof BarcodePrintJobStatus && $this->status->isInProgress();
    }
}
