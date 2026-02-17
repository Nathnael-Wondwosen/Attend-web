<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

header('Content-Type: text/html; charset=utf-8');

$root = dirname(__DIR__);
if (!is_file($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    http_response_code(500);
    echo 'vendor/autoload.php not found';
    exit;
}

require $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$app = require_once $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$opsKey = (string) env('OPS_RUN_KEY', '');
$givenKey = isset($_GET['key']) ? (string) $_GET['key'] : '';
if ($opsKey !== '' && !hash_equals($opsKey, $givenKey)) {
    http_response_code(403);
    echo '<h3>Forbidden</h3><p>Invalid or missing key.</p>';
    exit;
}

$classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$rows = [];
$error = null;

try {
    if ($classId > 0) {
        $parentPhones = DB::table('parents as p')
            ->selectRaw('p.student_id, MIN(p.id) as min_parent_id')
            ->whereNotNull('p.phone_number')
            ->whereRaw("TRIM(p.phone_number) <> ''")
            ->groupBy('p.student_id');

        $rows = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->leftJoinSub($parentPhones, 'pp', function ($join): void {
                $join->on('pp.student_id', '=', 's.id');
            })
            ->leftJoin('parents as p', 'p.id', '=', 'pp.min_parent_id')
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get([
                's.id',
                's.full_name',
                DB::raw("NULLIF(TRIM(s.phone_number), '') as student_phone"),
                DB::raw("NULLIF(TRIM(p.phone_number), '') as parent_phone"),
                DB::raw("COALESCE(NULLIF(TRIM(s.phone_number), ''), NULLIF(TRIM(p.phone_number), '')) as phone_number"),
                DB::raw("CASE WHEN NULLIF(TRIM(s.phone_number), '') IS NOT NULL THEN 'student' WHEN NULLIF(TRIM(p.phone_number), '') IS NOT NULL THEN 'parent' ELSE 'none' END as phone_source"),
            ]);
    }
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Phone Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        .card { border: 1px solid #ddd; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e4e4e4; padding: 8px; text-align: left; font-size: 14px; }
        th { background: #f8f8f8; }
        .ok { color: #0a7a2f; font-weight: 600; }
        .warn { color: #a66d00; font-weight: 600; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h2>Student Phone Test</h2>
    <div class="card">
        <form method="get">
            <?php if ($opsKey !== ''): ?>
                <input type="hidden" name="key" value="<?= htmlspecialchars($givenKey, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <label for="class_id">Class ID:</label>
            <input id="class_id" name="class_id" type="number" min="1" value="<?= $classId > 0 ? $classId : '' ?>" required>
            <button type="submit">Load</button>
        </form>
        <p class="muted">Resolution logic: <b>students.phone_number</b> first, fallback to <b>parents.phone_number</b>.</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="card"><b>Error:</b> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($classId > 0 && $error === null): ?>
        <div class="card">
            <p><b>Class ID:</b> <?= $classId ?> | <b>Rows:</b> <?= count($rows) ?></p>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Student Phone</th>
                        <th>Parent Phone</th>
                        <th>Resolved Phone</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int) $r->id ?></td>
                            <td><?= htmlspecialchars((string) $r->full_name, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($r->student_phone ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($r->parent_phone ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="<?= $r->phone_number ? 'ok' : 'warn' ?>">
                                <?= htmlspecialchars((string) ($r->phone_number ?? 'NO PHONE'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars((string) ($r->phone_source ?? 'none'), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>
