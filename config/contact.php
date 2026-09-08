<?php

return [
    'recipient' => array_values(array_filter(array_map('trim', explode(',', (string) env('CONTACT_EMAIL', ''))))),
];
