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
    // Prepare
    ['cmd' => 'optimize:clear', 'critical' => false],
    ['cmd' => 'config:clear', 'critical' => true],
    ['cmd' => 'route:clear', 'critical' => false],
    ['cmd' => 'view:clear', 'critical' => false],
    ['cmd' => 'cache:clear', 'critical' => false],

    // Deployment-safe DB steps
    ['cmd' => 'migrate:install --force', 'critical' => true],
    ['cmd' => 'migrate --force', 'critical' => true],
    ['cmd' => 'admin:ensure --update-existing', 'critical' => false],

    // Finalize
    ['cmd' => 'storage:link', 'critical' => false],
    ['cmd' => 'config:cache', 'critical' => true],
    ['cmd' => 'route:cache', 'critical' => true],
    ['cmd' => 'view:cache', 'critical' => false],
];

echo "Running refresh commands...\n\n";

foreach ($commands as $entry) {
    $command = (string) $entry['cmd'];
    $critical = (bool) $entry['critical'];
    echo ">>> php artisan {$command}\n";
    try {
        $parts = preg_split('/\s+/', trim($command)) ?: [];
        $name = array_shift($parts);
        $args = [];
        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                $opt = substr($part, 2);
                if ($opt === '') {
                    continue;
                }
                $kv = explode('=', $opt, 2);
                if (count($kv) === 2) {
                    $args['--'.$kv[0]] = $kv[1];
                } else {
                    $args['--'.$opt] = true;
                }
            } elseif ($part !== '') {
                $args[] = $part;
            }
        }

        $exit = $kernel->call($name ?: $command, $args);
        $out = $kernel->output();
        echo "exit={$exit}\n";
        if ($out !== '') {
            echo $out;
            if (!str_ends_with($out, "\n")) {
                echo "\n";
            }
        }
        if ($exit !== 0 && $critical) {
            echo "CRITICAL STEP FAILED. Stopping.\n";
            echo "DONE (FAILED)\n";
            exit(1);
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        if ($critical) {
            echo "CRITICAL STEP FAILED. Stopping.\n";
            echo "DONE (FAILED)\n";
            exit(1);
        }
    }
    echo "-----------------------------\n";
}

echo "DONE\n";
