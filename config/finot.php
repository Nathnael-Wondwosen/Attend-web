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
];

