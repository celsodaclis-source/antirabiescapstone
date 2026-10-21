<?php

return [
    // Injection sites approved by the clinic's protocol. Override with VACCINE_ADMINISTRATION_SITES in .env.
    'administration_sites' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('VACCINE_ADMINISTRATION_SITES', 'Left deltoid,Right thigh'))
    ))),
];
