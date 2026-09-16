<?php

return [
    'status' => [
        'pending' => 'Čeka generisanje',
        'processing' => 'U procesu generisanja',
        'completed' => 'Generisano',
        'failed' => 'Greška pri generisanju',
    ],

    'errors' => [
        'not_ready' => 'Inventarna knjiga još nije generisana.',
        'file_missing' => 'Generisani PDF nije pronađen. Pokrenite generisanje ponovo.',
    ],

    'pdf' => [
        'title' => 'Inventarna knjiga',
        'header' => [
            'order_number' => 'Inventarni broj',
            'seq_number' => 'Redni broj',
            'date' => 'Datum',
            'description' => 'Naslov i opis',
            'binding' => 'Vrsta poveza',
            'dimension' => 'Dimenzije',
            'origin' => 'Način nabavke',
            'price' => 'Cena',
            'udk' => 'Signatura',
            'notice' => 'Napomena',
        ],
    ],
];
