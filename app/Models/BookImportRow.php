<?php

namespace App\Models;

use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportFailureStageEnum;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'book_import_id',
    'row_number',
    'raw_payload',
    'input_overrides',
    'input_version',
    'resolved_payload',
    'field_sources',
    'isbn',
    'title',
    'inventory_number',
    'status',
    'failure_stage',
    'failure_code',
    'failure_message',
    'lookup_diagnostics',
    'attempts',
    'completed_attempts',
    'attempt_mode',
    'processing_requested_at',
    'book_id',
    'book_copy_id',
    'processed_at',
])]
class BookImportRow extends Model
{
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'status' => BookImportRowStatusEnum::class,
            'failure_stage' => BookImportFailureStageEnum::class,
            'failure_code' => BookImportFailureEnum::class,
            'attempt_mode' => BookImportModeEnum::class,
            'raw_payload' => 'array',
            'input_overrides' => 'array',
            'input_version' => 'integer',
            'resolved_payload' => 'array',
            'field_sources' => 'array',
            'lookup_diagnostics' => 'array',
            'attempts' => 'integer',
            'completed_attempts' => 'integer',
            'processing_requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BookImport, $this>
     */
    public function bookImport(): BelongsTo
    {
        return $this->belongsTo(BookImport::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<BookCopy, $this>
     */
    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }
}
