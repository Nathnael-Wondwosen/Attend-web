<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Roster Master Data Protection
    |--------------------------------------------------------------------------
    |
    | The school's roster tables (students/classes/enrollments/teachers/admins)
    | are considered master data owned by the mother system.
    |
    | This app must treat those tables as read-only in production.
    |
    */
    'roster' => [
        // Allows running seeders that create/modify roster tables. Keep false on production.
        'allow_seeding' => (bool) env('FINOT_ROSTER_ALLOW_SEEDING', false),
    ],

    'admin' => [
        // Optional: when true, /run also executes `php artisan admin:ensure`.
        // Keep false unless FINOT_ADMIN_* env vars are configured for deployment.
        'auto_ensure_on_ops_run' => (bool) env('FINOT_ADMIN_AUTO_ENSURE_ON_OPS_RUN', false),
        // Optional: when true, migrations trigger `admin:ensure` automatically.
        'auto_ensure_on_migrate' => (bool) env('FINOT_ADMIN_AUTO_ENSURE_ON_MIGRATE', false),
    ],

    'framework' => [
        // Safety default for shared mother-system databases:
        // do not create Laravel auth/session tables unless explicitly enabled.
        'allow_user_auth_tables' => (bool) env('FINOT_ALLOW_USER_AUTH_TABLES', false),
    ],
];
