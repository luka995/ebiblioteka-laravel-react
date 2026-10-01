<?php

return [

    'formats' => [
        'label' => 'Barcode printer (62×29 mm)',
        'a4' => 'A4 — 48 labels',
    ],

    'status' => [
        'pending' => 'Waiting to generate',
        'processing' => 'Generating',
        'completed' => 'Generated',
        'failed' => 'Generation failed',
    ],

    'errors' => [
        'not_ready' => 'The barcode PDF has not been generated yet.',
        'file_missing' => 'The generated PDF was not found. Start generation again.',
        'no_printable_copies' => 'There are no copies with a valid barcode to print.',
    ],

];
