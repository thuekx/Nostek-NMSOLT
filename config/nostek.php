<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Device write boundary
    |--------------------------------------------------------------------------
    |
    | Disabled by default. This gate covers commands that mutate OLT/ONT state,
    | including queued execution and interactive Telnet access. Read-only
    | polling, previews and locally persisted inventory remain available.
    |
    */
    'device_writes_enabled' => filter_var(
        env('NMSOLT_DEVICE_WRITES_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),
];
