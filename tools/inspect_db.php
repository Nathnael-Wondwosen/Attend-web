<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Keep output focused: schema + counts only (no sensitive row data).
$dbName = DB::getDatabaseName();

$tables = [
    'admins',
    'teachers',
    'students',
    'classes',
    'class_teachers',
    'class_enrollments',
    'att_sessions',
    'att_attendance',
    'att_session_tokens',
    'personal_access_tokens',
    'migrations',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
];

$out = [
    'database' => $dbName,
    'tables' => [],
];

foreach ($tables as $t) {
    try {
        $count = DB::table($t)->count();
    } catch (Throwable $e) {
        $out['tables'][$t] = ['exists' => false];
        continue;
    }

    $cols = DB::select(
        'SELECT column_name,data_type,is_nullable,column_key
         FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name=?
         ORDER BY ordinal_position',
        [$t]
    );

    $out['tables'][$t] = [
        'exists' => true,
        'rows' => (int) $count,
        'columns' => array_map(static function ($c) {
            return [
                'name' => $c->column_name,
                'type' => $c->data_type,
                'nullable' => $c->is_nullable === 'YES',
                'key' => $c->column_key ?: null,
            ];
        }, $cols),
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

