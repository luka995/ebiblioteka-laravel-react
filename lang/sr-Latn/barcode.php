<?php

return [

    'formats' => [
        'label' => 'Bar-kod štampač (62×29 mm)',
        'a4' => 'A4 — 48 nalepnica',
    ],

    'status' => [
        'pending' => 'Čeka generisanje',
        'processing' => 'U procesu generisanja',
        'completed' => 'Generisano',
        'failed' => 'Greška pri generisanju',
    ],

    'errors' => [
        'not_ready' => 'PDF sa bar-kodovima još nije generisan.',
        'file_missing' => 'Generisani PDF nije pronađen. Pokrenite generisanje ponovo.',
        'no_printable_copies' => 'Nema jedinica sa važećim bar-kodom za štampu.',
    ],

];
