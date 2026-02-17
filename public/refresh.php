<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);

if (!is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    http_response_code(500);
    echo "ERROR: artisan not found\n";
    exit;
}

if (!is_file($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    http_response_code(500);
    echo "ERROR: vendor/autoload.php not found\n";
    exit;
}

require $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$app = require_once $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$commands = [
    'optimize:clear',
    'config:clear',
    'route:clear',
    'view:clear',
    'cache:clear',
    'storage:link',
];

echo "Running refresh commands...\n\n";

foreach ($commands as $command) {
    echo ">>> php artisan {$command}\n";
    try {
        $exit = $kernel->call($command);
        $out = $kernel->output();
        echo "exit={$exit}\n";
        if ($out !== '') {
            echo $out;
            if (!str_ends_with($out, "\n")) {
                echo "\n";
            }
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    echo "-----------------------------\n";
}

echo "DONE\n";
