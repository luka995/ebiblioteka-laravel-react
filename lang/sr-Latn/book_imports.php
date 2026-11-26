<?php

return [
    'status' => [
        'pending' => 'Priprema pregleda',
        'ready' => 'Spremno za uvoz',
        'processing' => 'U obradi',
        'completed' => 'Završeno',
        'completed_with_errors' => 'Završeno sa greškama',
        'failed' => 'Neuspešno',
    ],

    'row_status' => [
        'pending' => 'Na čekanju',
        'processing' => 'U obradi',
        'imported' => 'Uvezeno',
        'failed' => 'Neuspešno',
    ],

    'modes' => [
        'isbn_check' => 'Provera preko ISBN-a',
        'excel_direct' => 'Direktan uvoz iz Excel-a',
    ],

    'failures' => [
        'missing_isbn' => 'ISBN nije unet',
        'invalid_isbn' => 'Neispravan ISBN',
        'invalid_field_value' => 'Neispravna vrednost polja',
        'isbn_not_found' => 'ISBN nije pronađen',
        'lookup_failed' => 'Pretraga ISBN-a nije uspela',
        'missing_title' => 'Naslov nije unet',
        'missing_inventory_number' => 'Inventarni broj nije unet',
        'invalid_inventory_number' => 'Neispravan inventarni broj',
        'duplicate_inventory_number' => 'Inventarni broj je već zauzet',
        'inventory_collision' => 'Kolizija inventarnog broja',
        'duplicate_seq_number' => 'Redni broj je već zauzet',
        'file_unreadable' => 'Fajl nije moguće pročitati',
        'missing_columns' => 'Nedostaju obavezne kolone',
        'duplicate_headers' => 'Duplirana zaglavlja kolona',
        'no_rows' => 'Fajl ne sadrži podatke',
        'too_many_rows' => 'Prekoračen je maksimalan broj redova',
        'job_failed' => 'Obrada nije uspela',
        'unexpected_error' => 'Neočekivana greška',
    ],
];
