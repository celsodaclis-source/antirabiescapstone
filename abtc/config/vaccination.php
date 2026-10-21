<?php

return [
    // Populate with the administration sites approved by the clinic's protocol.
    // Empty by default so the application does not invent clinical site options.
    'administration_sites' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('VACCINE_ADMINISTRATION_SITES', ''))
    ))),
];
