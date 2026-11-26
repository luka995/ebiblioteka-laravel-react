<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;
use Override;

enum BookImportFailureEnum: string
{
    use EnumToArray;

    case MissingIsbn = 'missing_isbn';
    case InvalidIsbn = 'invalid_isbn';
    case InvalidFieldValue = 'invalid_field_value';
    case IsbnNotFound = 'isbn_not_found';
    case LookupFailed = 'lookup_failed';
    case MissingTitle = 'missing_title';
    case MissingInventoryNumber = 'missing_inventory_number';
    case InvalidInventoryNumber = 'invalid_inventory_number';
    case DuplicateInventoryNumber = 'duplicate_inventory_number';
    case InventoryCollision = 'inventory_collision';
    case DuplicateSeqNumber = 'duplicate_seq_number';
    case FileUnreadable = 'file_unreadable';
    case MissingColumns = 'missing_columns';
    case DuplicateHeaders = 'duplicate_headers';
    case NoRows = 'no_rows';
    case TooManyRows = 'too_many_rows';
    case JobFailed = 'job_failed';
    case UnexpectedError = 'unexpected_error';

    #[Override]
    public function getLabel(): string
    {
        return match ($this) {
            self::MissingIsbn => __('book_imports.failures.missing_isbn'),
            self::InvalidIsbn => __('book_imports.failures.invalid_isbn'),
            self::InvalidFieldValue => __('book_imports.failures.invalid_field_value'),
            self::IsbnNotFound => __('book_imports.failures.isbn_not_found'),
            self::LookupFailed => __('book_imports.failures.lookup_failed'),
            self::MissingTitle => __('book_imports.failures.missing_title'),
            self::MissingInventoryNumber => __('book_imports.failures.missing_inventory_number'),
            self::InvalidInventoryNumber => __('book_imports.failures.invalid_inventory_number'),
            self::DuplicateInventoryNumber => __('book_imports.failures.duplicate_inventory_number'),
            self::InventoryCollision => __('book_imports.failures.inventory_collision'),
            self::DuplicateSeqNumber => __('book_imports.failures.duplicate_seq_number'),
            self::FileUnreadable => __('book_imports.failures.file_unreadable'),
            self::MissingColumns => __('book_imports.failures.missing_columns'),
            self::DuplicateHeaders => __('book_imports.failures.duplicate_headers'),
            self::NoRows => __('book_imports.failures.no_rows'),
            self::TooManyRows => __('book_imports.failures.too_many_rows'),
            self::JobFailed => __('book_imports.failures.job_failed'),
            self::UnexpectedError => __('book_imports.failures.unexpected_error'),
        };
    }
}
