<?php

return [
    'errors' => [
        'active_import' => 'This library already has an active import.',
        'invalid_field' => 'Invalid value in column ":field".',
        'filename_too_long' => 'The filename must not exceed 255 characters.',
    ],

    'status' => [
        'pending' => 'Preparing preview',
        'ready' => 'Ready to import',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'completed_with_errors' => 'Completed with errors',
        'failed' => 'Failed',
    ],

    'row_status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'imported' => 'Imported',
        'failed' => 'Failed',
    ],

    'modes' => [
        'isbn_check' => 'ISBN lookup',
        'excel_direct' => 'Direct Excel import',
    ],

    'failures' => [
        'missing_isbn' => 'ISBN is missing',
        'invalid_isbn' => 'Invalid ISBN',
        'invalid_field_value' => 'Invalid field value',
        'isbn_not_found' => 'ISBN not found',
        'lookup_failed' => 'ISBN lookup failed',
        'missing_title' => 'Title is missing',
        'missing_inventory_number' => 'Inventory number is missing',
        'invalid_inventory_number' => 'Invalid inventory number',
        'duplicate_inventory_number' => 'Inventory number is already in use',
        'inventory_collision' => 'Inventory number collision',
        'duplicate_seq_number' => 'Sequence number is already in use',
        'file_unreadable' => 'The file could not be read',
        'missing_columns' => 'Required columns are missing',
        'duplicate_headers' => 'Duplicate column headers',
        'no_rows' => 'The file contains no data',
        'too_many_rows' => 'The maximum number of rows was exceeded',
        'job_failed' => 'Processing failed',
        'unexpected_error' => 'Unexpected error',
    ],
];
