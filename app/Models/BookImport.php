<?php

namespace App\Models;

use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'library_id',
    'user_id',
    'original_filename',
    'file_path',
    'file_size',
    'sheet_name',
    'status',
    'has_inventory_column',
    'headers',
    'column_map',
    'total_rows',
    'processed_rows',
    'imported_rows',
    'failed_rows',
    'completed_attempts',
    'processing_token',
    'lease_expires_at',
    'failure_code',
    'failure_message',
    'started_at',
    'finished_at',
])]
class BookImport extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BookImportStatusEnum::class,
            'failure_code' => BookImportFailureEnum::class,
            'file_size' => 'integer',
            'has_inventory_column' => 'boolean',
            'headers' => 'array',
            'column_map' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'imported_rows' => 'integer',
            'failed_rows' => 'integer',
            'completed_attempts' => 'integer',
            'lease_expires_at' => 'datetime',
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

    /**
     * @return HasMany<BookImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(BookImportRow::class);
    }
}
