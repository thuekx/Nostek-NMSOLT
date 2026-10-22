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

    /*
    |--------------------------------------------------------------------------
    | History retention (NMS-07-B)
    |--------------------------------------------------------------------------
    |
    | Applied daily by `history:prune` (03:30). Alarm events are pruned only
    | after they are cleared; active and pending alarms are never removed.
    | Audit logs are kept longer because they are security evidence. OLT
    | config backups keep the newest N per OLT plus the first successful
    | backup of each month for the configured number of months.
    |
    */
    'retention' => [
        'polling_events_days' => (int) env('NMSOLT_RETENTION_POLLING_DAYS', 30),
        'cleared_alarm_days' => (int) env('NMSOLT_RETENTION_CLEARED_ALARM_DAYS', 180),
        'audit_log_days' => (int) env('NMSOLT_RETENTION_AUDIT_DAYS', 400),
        'config_backups_keep_latest' => (int) env('NMSOLT_RETENTION_CONFIG_BACKUPS_LATEST', 7),
        'config_backups_monthly_months' => (int) env('NMSOLT_RETENTION_CONFIG_BACKUPS_MONTHS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Poll queue (NMS-07-B)
    |--------------------------------------------------------------------------
    |
    | OLT polls run on their own queue so a slow OLT or a long report job
    | cannot hold back the others. The container runs a dedicated worker
    | pool for it (NMSOLT_POLL_WORKERS); the general worker also serves it.
    |
    */
    'poll_queue' => env('NMSOLT_POLL_QUEUE', 'polling'),
];
