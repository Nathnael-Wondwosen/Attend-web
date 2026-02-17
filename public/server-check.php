<?php

declare(strict_types=1);

/**
 * Temporary diagnostics page for environments without terminal access.
 * Delete this file after debugging.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$envPath = $root.'/.env';

function readEnvValue(string $envPath, string $key): ?string
{
    if (!is_file($envPath)) {
        return null;
    }

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return null;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_starts_with($line, $key.'=')) {
            continue;
        }

        $value = substr($line, strlen($key) + 1);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        return $value;
    }

    return null;
}

$opsRunKey = readEnvValue($envPath, 'OPS_RUN_KEY');
$providedKey = (string)($_GET['key'] ?? '');
if ($opsRunKey !== null && $opsRunKey !== '' && !hash_equals($opsRunKey, $providedKey)) {
    http_response_code(404);
    exit('Not Found');
}

$checks = [];

$checks[] = [
    'name' => 'PHP version (must be >= 8.2)',
    'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'value' => PHP_VERSION,
];

$requiredExtensions = ['ctype', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml'];
foreach ($requiredExtensions as $ext) {
    $checks[] = [
        'name' => "PHP extension: {$ext}",
        'ok' => extension_loaded($ext),
        'value' => extension_loaded($ext) ? 'loaded' : 'missing',
    ];
}

$vendorAutoload = $root.'/vendor/autoload.php';
$bootstrapApp = $root.'/bootstrap/app.php';
$storageDir = $root.'/storage';
$cacheDir = $root.'/bootstrap/cache';

$checks[] = [
    'name' => 'vendor/autoload.php exists',
    'ok' => is_file($vendorAutoload),
    'value' => is_file($vendorAutoload) ? 'yes' : 'no',
];

$checks[] = [
    'name' => 'bootstrap/app.php exists',
    'ok' => is_file($bootstrapApp),
    'value' => is_file($bootstrapApp) ? 'yes' : 'no',
];

$checks[] = [
    'name' => '.env exists',
    'ok' => is_file($envPath),
    'value' => is_file($envPath) ? 'yes' : 'no',
];

$checks[] = [
    'name' => 'storage writable',
    'ok' => is_dir($storageDir) && is_writable($storageDir),
    'value' => (is_dir($storageDir) ? 'dir exists' : 'missing').' / '.(is_writable($storageDir) ? 'writable' : 'not writable'),
];

$checks[] = [
    'name' => 'bootstrap/cache writable',
    'ok' => is_dir($cacheDir) && is_writable($cacheDir),
    'value' => (is_dir($cacheDir) ? 'dir exists' : 'missing').' / '.(is_writable($cacheDir) ? 'writable' : 'not writable'),
];

$bootstrapResult = 'not attempted';
if (is_file($vendorAutoload) && is_file($bootstrapApp)) {
    try {
        require_once $vendorAutoload;
        require $bootstrapApp;
        $bootstrapResult = 'ok';
    } catch (Throwable $e) {
        $bootstrapResult = 'error: '.$e->getMessage();
    }
}

$checks[] = [
    'name' => 'Laravel bootstrap',
    'ok' => $bootstrapResult === 'ok',
    'value' => $bootstrapResult,
];

$logPath = $root.'/storage/logs/laravel.log';
$logTail = '';
if (is_file($logPath) && is_readable($logPath)) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $tail = array_slice($lines, -80);
        $logTail = implode("\n", $tail);
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #0b1020; color: #e5e7eb; }
        h1 { margin-bottom: 8px; }
        .hint { color: #9ca3af; margin-bottom: 16px; }
        table { border-collapse: collapse; width: 100%; background: #111827; }
        th, td { border: 1px solid #1f2937; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #1f2937; }
        .ok { color: #34d399; font-weight: bold; }
        .bad { color: #f87171; font-weight: bold; }
        pre { background: #111827; border: 1px solid #1f2937; padding: 12px; white-space: pre-wrap; word-wrap: break-word; }
        code { background: #1f2937; padding: 2px 4px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Laravel Server Check</h1>
    <p class="hint">Temporary diagnostics endpoint. Delete <code>public/server-check.php</code> after fixing production.</p>

    <table>
        <thead>
            <tr>
                <th>Check</th>
                <th>Status</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
            <tr>
                <td><?= htmlspecialchars($check['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'PASS' : 'FAIL' ?></td>
                <td><?= htmlspecialchars((string)$check['value'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Laravel Log Tail</h2>
    <?php if ($logTail !== ''): ?>
        <pre><?= htmlspecialchars($logTail, ENT_QUOTES, 'UTF-8') ?></pre>
    <?php else: ?>
        <p>No readable <code>storage/logs/laravel.log</code> found yet.</p>
    <?php endif; ?>
</body>
</html>
