<?php

return [
    'lookup_status' => [
        'found' => 'Пронађено',
        'not_found' => 'Није пронађено',
        'failed' => 'Провера није успела',
    ],
    'errors' => [
        'not_ready' => 'Увоз мора бити спреман пре покретања.',
        'invalid_selection' => 'Изаберите редове на чекању који припадају овом увозу.',
        'active_import' => 'Ова библиотека већ има активан увоз.',
        'invalid_field' => 'Неисправна вредност у колони „:field“.',
        'filename_too_long' => 'Назив фајла не сме бити дужи од 255 знакова.',
    ],

    'status' => [
        'pending' => 'Припрема прегледа',
        'ready' => 'Спремно за увоз',
        'processing' => 'У обради',
        'completed' => 'Завршено',
        'completed_with_errors' => 'Завршено са грешкама',
        'failed' => 'Неуспешно',
    ],

    'row_status' => [
        'pending' => 'На чекању',
        'processing' => 'У обради',
        'imported' => 'Увезено',
        'failed' => 'Неуспешно',
    ],

    'modes' => [
        'isbn_check' => 'Провера преко ISBN-а',
        'excel_direct' => 'Директан увоз из Excel-а',
    ],

    'failures' => [
        'missing_isbn' => 'ISBN није унет',
        'invalid_isbn' => 'Неисправан ISBN',
        'invalid_field_value' => 'Неисправна вредност поља',
        'isbn_not_found' => 'ISBN није пронађен',
        'lookup_failed' => 'Претрага ISBN-а није успела',
        'missing_title' => 'Наслов није унет',
        'missing_inventory_number' => 'Инвентарни број није унет',
        'invalid_inventory_number' => 'Неисправан инвентарни број',
        'duplicate_inventory_number' => 'Инвентарни број је већ заузет',
        'inventory_collision' => 'Колизија инвентарног броја',
        'duplicate_seq_number' => 'Редни број је већ заузет',
        'file_unreadable' => 'Фајл није могуће прочитати',
        'missing_columns' => 'Недостају обавезне колоне',
        'duplicate_headers' => 'Дуплирана заглавља колона',
        'no_rows' => 'Фајл не садржи податке',
        'too_many_rows' => 'Прекорачен је максималан број редова',
        'job_failed' => 'Обрада није успела',
        'unexpected_error' => 'Неочекивана грешка',
    ],
];
