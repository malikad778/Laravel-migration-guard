<?php

return [

    // Environments where guard is active.
    // Empty array = always active.
    'environments' => ['production', 'staging'],

    // 'warn'  -> display warning, continue migration
    // 'block' -> throw exception, stop migration
    'mode' => env('MIGRATION_GUARD_MODE', 'warn'),

    // Which checks to enable. true = enabled, false = disabled.
    'checks' => [
        'drop_column'         => true,
        'drop_table'          => true,
        'rename_column'       => true,
        'rename_table'        => true,
        'add_column_not_null' => true,
        'change_column_type'  => true,
        'add_index'           => true,
        'modify_primary_key'  => true,
        'truncate'            => true,
    ],

    // Tables that trigger an extra warning regardless of operation type.
    // Use for tables with millions of rows or business-critical data.
    'critical_tables' => [
        // 'users', 'orders', 'payments',
    ],

    // (v1.1.0) Row count threshold above which a table is treated as critical for index checks.
    // Requires a live DB connection during analysis.
    'row_threshold' => env('MIGRATION_GUARD_ROW_THRESHOLD', 500000),

    // Suppress a specific check on a specific table (or specific column on a table).
    // Use when you have confirmed the operation is safe for your specific situation.
    'ignore' => [
        // ['check' => 'drop_column', 'table' => 'legacy_logs'],
        // ['check' => 'add_column_not_null', 'table' => 'users', 'column' => 'migrated_at'],
    ],

    // (v1.2.0) Notification recipients for DangerousMigrationBypassed alerts.
    // Fires when a developer presses Y to continue past a guard warning in production/staging.
    // Set MIGRATION_GUARD_MAIL_TO and/or MIGRATION_GUARD_SLACK_WEBHOOK in your .env.
    'notifications' => [
        'mail'  => ['to' => env('MIGRATION_GUARD_MAIL_TO')],
        'slack' => ['webhook' => env('MIGRATION_GUARD_SLACK_WEBHOOK')],
    ],
];
