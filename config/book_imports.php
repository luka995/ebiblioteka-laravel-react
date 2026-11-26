<?php

return [
    'disk' => 'imports',
    'log_channel' => 'imports',
    'queue' => 'imports',
    'max_file_size_kb' => 20 * 1024,
    'max_rows' => 20000,
    'header_scan_rows' => 20,
    'prepare_chunk_size' => 500,
    'standard_batch_size' => 20,
    'excel_batch_size' => 100,
    'timeout' => 300,
    'tries' => 3,
    'lease_seconds' => 360,
    'recovery_after_seconds' => 900,
];
