<?php

return [
    'status' => [
        'pending' => 'Pending generation',
        'processing' => 'Generating',
        'completed' => 'Generated',
        'failed' => 'Generation failed',
    ],

    'errors' => [
        'not_ready' => 'The inventory book has not been generated yet.',
        'file_missing' => 'The generated PDF could not be found. Please generate it again.',
    ],

    'pdf' => [
        'title' => 'Inventory book',
        'header' => [
            'order_number' => 'Inventory number',
            'seq_number' => 'Ordinal number',
            'date' => 'Date',
            'description' => 'Title and description',
            'binding' => 'Binding',
            'dimension' => 'Dimensions',
            'origin' => 'Acquisition',
            'price' => 'Price',
            'udk' => 'Signature',
            'notice' => 'Note',
        ],
    ],
];
