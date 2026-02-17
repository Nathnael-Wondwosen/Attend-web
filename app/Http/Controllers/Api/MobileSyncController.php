<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttSession;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MobileSyncController extends Controller
{
    protected const STATUSES = ['present', 'absent', 'permission'];
    protected const SUBMISSION_EDIT_DAYS = 7;
    protected array $columnCache = [];

    public function snapshot(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof AttTeacherAccount) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $teacherId = (int) $user->teacher_id;

        // Classes assigned to this teacher (same logic as TeacherDataController@classes).
        $classes = DB::table('classes as c')
            ->select(['c.id', 'c.name', 'c.grade', 'c.section'])
            ->selectSub(function ($q) {
                $q->from('class_enrollments as ce')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('ce.class_id', 'c.id')
                    ->where('ce.status', 'active');
            }, 'students_count')
            ->where(function ($w) use ($teacherId) {
                $w->whereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('att_teacher_class_assignments as tca')
                        ->whereColumn('tca.class_id', 'c.id')
                        ->where('tca.teacher_id', $teacherId)
                        ->where('tca.is_active', 1);
                })->orWhereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('class_teachers as ct')
                        ->whereColumn('ct.class_id', 'c.id')
                        ->where('ct.teacher_id', $teacherId)
                        ->where('ct.is_active', 1);
                })->orWhere('c.teacher_id', $teacherId);
            })
            ->orderBy('c.grade')
            ->orderBy('c.section')
            ->get();

        $classIds = $classes->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

        // Roster snapshot: active enrollments only.
        $rosters = [];
        if (!empty($classIds)) {
            $rows = DB::table('class_enrollments as ce')
                ->join('students as s', 's.id', '=', 'ce.student_id')
                ->whereIn('ce.class_id', $classIds)
                ->where('ce.status', 'active')
                ->orderBy('s.full_name')
                ->get([
                    'ce.class_id',
                    's.id as student_id',
                    's.full_name',
                    's.gender',
                    's.current_grade',
                ]);

            foreach ($rows as $r) {
                $cid = (int) $r->class_id;
                if (!isset($rosters[$cid])) {
                    $rosters[$cid] = [];
                }
                $rosters[$cid][] = [
                    'id' => (int) $r->student_id,
                    'full_name' => (string) $r->full_name,
                    'gender' => $r->gender,
                    'current_grade' => $r->current_grade,
                ];
            }
        }

        return response()->json([
            'generated_at' => now()->toISOString(),
            'teacher' => [
                'teacher_account_id' => (int) $user->id,
                'teacher_id' => (int) $user->teacher_id,
            ],
            'classes' => $classes,
            'rosters' => $rosters,
        ]);
    }

    public function sync(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof AttTeacherAccount) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.client_session_id' => ['required', 'uuid'],
            'items.*.class_id' => ['required', 'integer'],
            'items.*.attendance_date' => ['required', 'date'],
            'items.*.submit' => ['nullable', 'boolean'],
            'items.*.updates' => ['nullable', 'array', 'max:500'],
            'items.*.updates.*.student_id' => ['required_with:items.*.updates', 'integer'],
            'items.*.updates.*.status' => ['required_with:items.*.updates', Rule::in(self::STATUSES)],
            'items.*.updates.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.updates.*.marked_at' => ['nullable', 'date'],
        ]);

        $deviceId = isset($data['device_id']) ? trim((string) $data['device_id']) : null;
        $items = $data['items'];
        $syncLedgerAvailable = Schema::hasTable('att_mobile_sync');

        // Pre-check class assignment for all items.
        $classIds = collect($items)->pluck('class_id')->unique()->values()->map(fn ($v) => (int) $v)->all();
        $allowedClassIds = $this->teacherAllowedClassIds((int) $user->teacher_id, $classIds);
        $allowedSet = array_fill_keys($allowedClassIds, true);

        $responses = [];
        foreach ($items as $item) {
            $clientSessionId = (string) $item['client_session_id'];
            $classId = (int) $item['class_id'];
            $attendanceDate = Carbon::parse($item['attendance_date'])->toDateString();
            $submit = (bool) ($item['submit'] ?? false);
            $updates = is_array($item['updates'] ?? null) ? $item['updates'] : [];

            if (!isset($allowedSet[$classId])) {
                $responses[] = [
                    'client_session_id' => $clientSessionId,
                    'status' => 'rejected',
                    'code' => 403,
                    'message' => 'Teacher not assigned to this class',
                ];
                continue;
            }

            // Normalize payload for idempotency hash.
            $norm = [
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'submit' => $submit ? 1 : 0,
                'updates' => collect($updates)->map(function ($u) {
                    return [
                        'student_id' => (int) $u['student_id'],
                        'status' => (string) $u['status'],
                        'note' => isset($u['note']) ? (string) $u['note'] : null,
                        'marked_at' => isset($u['marked_at']) ? Carbon::parse($u['marked_at'])->toISOString() : null,
                    ];
                })->sortBy('student_id')->values()->all(),
            ];
            $payloadHash = hash('sha256', json_encode($norm, JSON_UNESCAPED_SLASHES));

            $existingSync = $syncLedgerAvailable
                ? DB::table('att_mobile_sync')->where('client_session_id', $clientSessionId)->first()
                : null;
            if ($existingSync) {
                if ((string) $existingSync->payload_hash !== $payloadHash) {
                    $responses[] = [
                        'client_session_id' => $clientSessionId,
                        'status' => 'conflict',
                        'code' => 409,
                        'message' => 'client_session_id already used with a different payload',
                    ];
                    continue;
                }

                $prev = $existingSync->result_json ? json_decode((string) $existingSync->result_json, true) : null;
                $responses[] = $prev ?: [
                    'client_session_id' => $clientSessionId,
                    'status' => 'applied',
                    'session_id' => $existingSync->session_id ? (int) $existingSync->session_id : null,
                ];
                continue;
            }

            // Validate student membership if updates provided.
            if (!empty($updates)) {
                $studentIds = collect($updates)->pluck('student_id')->unique()->values()->map(fn ($v) => (int) $v);
                $activeMembers = DB::table('class_enrollments')
                    ->where('class_id', $classId)
                    ->where('status', 'active')
                    ->whereIn('student_id', $studentIds)
                    ->pluck('student_id')
                    ->map(fn ($v) => (int) $v);
                $activeSet = array_fill_keys($activeMembers->all(), true);
                $invalid = $studentIds->filter(fn ($id) => !isset($activeSet[(int) $id]))->values();
                if ($invalid->isNotEmpty()) {
                    $res = [
                        'client_session_id' => $clientSessionId,
                        'status' => 'rejected',
                        'code' => 422,
                        'message' => 'Some students are not in this class',
                        'invalid_student_ids' => $invalid->take(25)->all(),
                    ];
                    $this->recordSync($user->id, $classId, $attendanceDate, $deviceId, $clientSessionId, $payloadHash, null, $res);
                    $responses[] = $res;
                    continue;
                }
            }

            $res = DB::transaction(function () use ($user, $classId, $attendanceDate, $submit, $updates, $deviceId, $clientSessionId, $payloadHash) {
                /** @var AttSession|null $session */
                $sessionQuery = AttSession::where('class_id', $classId);
                if ($this->hasTableColumn('att_sessions', 'attendance_date')) {
                    $sessionQuery->where('attendance_date', $attendanceDate);
                } else {
                    $sessionQuery->whereDate('started_at', $attendanceDate);
                }
                $session = $sessionQuery->lockForUpdate()->first();

                if ($session && $this->isSessionLocked($session)) {
                    $out = [
                        'client_session_id' => $clientSessionId,
                        'status' => 'conflict',
                        'code' => 409,
                        'message' => 'Attendance is locked',
                        'session_id' => (int) $session->id,
                        'workflow_status' => (string) ($session->workflow_status ?? 'draft'),
                        'editable_until' => $this->editableUntil($session)?->toISOString(),
                    ];
                    $this->recordSync($user->id, $classId, $attendanceDate, $deviceId, $clientSessionId, $payloadHash, (int) $session->id, $out);
                    return $out;
                }

                if (!$session) {
                    $sessionPayload = [
                        'class_id' => $classId,
                        'attendance_date' => $attendanceDate,
                        'academic_year' => null,
                        'term' => null,
                        'status' => 'open',
                        'workflow_status' => 'draft',
                        'started_by' => (int) $user->teacher_id,
                        'notes' => null,
                        'current_token' => null,
                        'token_expires_at' => null,
                    ];

                    $session = AttSession::create($this->filterToExistingColumns('att_sessions', $sessionPayload));
                }

                $updatedCount = 0;
                if (!empty($updates)) {
                    $now = now();
                    $rows = collect($updates)->map(function ($u) use ($session, $classId, $now, $user) {
                        $markedAt = isset($u['marked_at']) ? Carbon::parse($u['marked_at']) : $now;
                        return $this->filterToExistingColumns('att_attendance', [
                            'session_id' => (int) $session->id,
                            'class_id' => $classId,
                            'student_id' => (int) $u['student_id'],
                            'status' => (string) $u['status'],
                            'method' => 'manual',
                            'marked_by' => (int) $user->teacher_id,
                            'note' => isset($u['note']) ? (string) $u['note'] : null,
                            'marked_at' => $markedAt,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    })->all();

                    $updateCols = array_values(array_filter(
                        ['class_id', 'status', 'method', 'marked_by', 'note', 'marked_at', 'updated_at'],
                        fn ($col) => $this->hasTableColumn('att_attendance', $col)
                    ));
                    $this->upsertAttendanceRows($rows, $updateCols);
                    $updatedCount = count($rows);
                }

                if ($submit && (($session->workflow_status ?? 'draft') !== 'submitted')) {
                    // Insert missing as absent (active roster only).
                    $memberIds = DB::table('class_enrollments')
                        ->where('class_id', $classId)
                        ->where('status', 'active')
                        ->pluck('student_id')
                        ->map(fn ($v) => (int) $v)
                        ->all();

                    if (!empty($memberIds)) {
                        $already = DB::table('att_attendance')
                            ->where('session_id', (int) $session->id)
                            ->whereIn('student_id', $memberIds)
                            ->pluck('student_id')
                            ->map(fn ($v) => (int) $v)
                            ->all();

                        $alreadySet = array_fill_keys($already, true);
                        $now = now();
                        $missing = [];
                        foreach ($memberIds as $sid) {
                            if (!isset($alreadySet[$sid])) {
                                $missing[] = [
                                    'session_id' => (int) $session->id,
                                    'class_id' => $classId,
                                    'student_id' => $sid,
                                    'status' => 'absent',
                                    'method' => 'manual',
                                    'marked_by' => (int) $user->teacher_id,
                                    'marked_at' => $now,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                        }
                        if (!empty($missing)) {
                            $missing = array_map(
                                fn ($row) => $this->filterToExistingColumns('att_attendance', $row),
                                $missing
                            );
                            $this->insertAttendanceRows($missing);
                        }
                    }

                    $session->update($this->filterToExistingColumns('att_sessions', [
                        'workflow_status' => 'submitted',
                        'submitted_at' => now(),
                        'submitted_by' => (int) $user->teacher_id,
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]));
                }

                $out = [
                    'client_session_id' => $clientSessionId,
                    'status' => 'applied',
                    'code' => 200,
                    'session_id' => (int) $session->id,
                    'workflow_status' => (string) ($session->workflow_status ?? 'draft'),
                    'locked' => $this->isSessionLocked($session),
                    'editable_until' => $this->editableUntil($session)?->toISOString(),
                    'updated' => $updatedCount,
                    'submitted' => $submit ? true : false,
                ];

                $this->recordSync($user->id, $classId, $attendanceDate, $deviceId, $clientSessionId, $payloadHash, (int) $session->id, $out);

                return $out;
            });

            $responses[] = $res;
        }

        return response()->json([
            'synced_at' => now()->toISOString(),
            'count' => count($responses),
            'results' => $responses,
        ]);
    }

    public function sessions(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof AttTeacherAccount) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($data['limit'] ?? 50);
        $mineRaw = $request->query('mine', $request->input('mine', '1'));
        if (is_bool($mineRaw)) {
            $mine = $mineRaw;
        } else {
            $norm = strtolower(trim((string) $mineRaw));
            if (in_array($norm, ['1', 'true', 'yes', 'on'], true)) {
                $mine = true;
            } elseif (in_array($norm, ['0', 'false', 'no', 'off'], true)) {
                $mine = false;
            } else {
                $mine = true;
            }
        }

        $allowedClassIds = $this->teacherAllClassIds((int) $user->teacher_id);
        if (empty($allowedClassIds)) {
            return response()->json(['data' => []]);
        }

        $hasAttendanceDate = $this->hasTableColumn('att_sessions', 'attendance_date');
        $hasWorkflowStatus = $this->hasTableColumn('att_sessions', 'workflow_status');
        $hasSubmittedAt = $this->hasTableColumn('att_sessions', 'submitted_at');

        $q = DB::table('att_sessions as s')
            ->whereIn('s.class_id', $allowedClassIds);

        if ($hasAttendanceDate) {
            $q->orderByDesc('s.attendance_date');
        } else {
            $q->orderByDesc('s.started_at');
        }
        $q->orderByDesc('s.id');

        if (isset($data['class_id'])) {
            $cid = (int) $data['class_id'];
            $allowedSet = array_fill_keys($allowedClassIds, true);
            if (!isset($allowedSet[(int) $cid])) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $q->where('s.class_id', $cid);
        }

        if (!empty($data['from'])) {
            $from = Carbon::parse($data['from'])->toDateString();
            if ($hasAttendanceDate) {
                $q->where('s.attendance_date', '>=', $from);
            } else {
                $q->whereDate('s.started_at', '>=', $from);
            }
        }
        if (!empty($data['to'])) {
            $to = Carbon::parse($data['to'])->toDateString();
            if ($hasAttendanceDate) {
                $q->where('s.attendance_date', '<=', $to);
            } else {
                $q->whereDate('s.started_at', '<=', $to);
            }
        }
        if ($mine) {
            $q->where('s.started_by', (int) $user->teacher_id);
        }

        $selects = [
            's.id',
            's.class_id',
            's.started_by',
        ];
        if ($hasAttendanceDate) {
            $selects[] = 's.attendance_date';
        } else {
            $selects[] = DB::raw('DATE(s.started_at) as attendance_date');
        }
        if ($hasWorkflowStatus) {
            $selects[] = 's.workflow_status';
        } else {
            $selects[] = DB::raw("'draft' as workflow_status");
        }
        if ($hasSubmittedAt) {
            $selects[] = 's.submitted_at';
        } else {
            $selects[] = DB::raw('NULL as submitted_at');
        }

        $rows = $q->limit($limit)->get($selects)->map(function ($r) {
            $workflow = (string) ($r->workflow_status ?? 'draft');
            $submittedAt = $r->submitted_at ? Carbon::parse($r->submitted_at) : null;
            $locked = false;
            $editableUntil = null;
            if ($workflow === 'submitted') {
                if (!$submittedAt) {
                    $locked = true;
                } else {
                    $editableUntil = $submittedAt->copy()->addDays(self::SUBMISSION_EDIT_DAYS);
                    $locked = now()->greaterThan($editableUntil);
                }
            }
            return [
                'id' => (int) $r->id,
                'class_id' => (int) $r->class_id,
                'attendance_date' => (string) $r->attendance_date,
                'workflow_status' => $workflow,
                'submitted_at' => $submittedAt ? $submittedAt->toISOString() : null,
                'locked' => $locked,
                'editable_until' => $editableUntil ? $editableUntil->toISOString() : null,
            ];
        })->values();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function deleteSession(Request $request, int $sessionId)
    {
        $user = $request->user();
        if (!$user instanceof AttTeacherAccount) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $session = AttSession::findOrFail($sessionId);
        $allowedSet = array_fill_keys($this->teacherAllowedClassIds((int) $user->teacher_id, [(int) $session->class_id]), true);
        if (!isset($allowedSet[(int) $session->class_id])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Only allow deleting sessions created by this teacher account (prevents nuking other teacher/admin work).
        if ((int) ($session->started_by ?? 0) !== (int) $user->teacher_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($this->isSessionLocked($session)) {
            return response()->json(['message' => 'Attendance is locked'], 423);
        }

        DB::transaction(function () use ($sessionId) {
            DB::table('att_attendance')->where('session_id', $sessionId)->delete();
            DB::table('att_audit_logs')->where('session_id', $sessionId)->delete();
            DB::table('att_session_tokens')->where('session_id', $sessionId)->delete();
            DB::table('att_sessions')->where('id', $sessionId)->delete();
        });

        return response()->json([
            'message' => 'deleted',
            'session_id' => (int) $sessionId,
        ]);
    }

    protected function recordSync(
        int $teacherAccountId,
        int $classId,
        string $attendanceDate,
        ?string $deviceId,
        string $clientSessionId,
        string $payloadHash,
        ?int $sessionId,
        array $result
    ): void {
        // Sync ledger table is optional for backwards compatibility with
        // deployments that missed this migration. Attendance sync must still work.
        if (!Schema::hasTable('att_mobile_sync')) {
            return;
        }

        try {
            DB::table('att_mobile_sync')->insert([
                'teacher_account_id' => $teacherAccountId,
                'session_id' => $sessionId,
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'device_id' => $deviceId,
                'client_session_id' => $clientSessionId,
                'payload_hash' => $payloadHash,
                'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never fail sync because ledger logging failed.
        }
    }

    protected function upsertAttendanceRows(array $rows, array $updateCols): void
    {
        if (empty($rows)) {
            return;
        }

        try {
            DB::table('att_attendance')->upsert($rows, ['session_id', 'student_id'], $updateCols);
        } catch (\Throwable $e) {
            // Backward-compat for older enum values that use "excused" instead of "permission".
            $fallback = array_map(function ($r) {
                if (($r['status'] ?? null) === 'permission') {
                    $r['status'] = 'excused';
                }
                return $r;
            }, $rows);
            DB::table('att_attendance')->upsert($fallback, ['session_id', 'student_id'], $updateCols);
        }
    }

    protected function insertAttendanceRows(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        try {
            DB::table('att_attendance')->insertOrIgnore($rows);
        } catch (\Throwable $e) {
            // Backward-compat for older enum values that use "excused" instead of "permission".
            $fallback = array_map(function ($r) {
                if (($r['status'] ?? null) === 'permission') {
                    $r['status'] = 'excused';
                }
                return $r;
            }, $rows);
            DB::table('att_attendance')->insertOrIgnore($fallback);
        }
    }

    protected function filterToExistingColumns(string $table, array $payload): array
    {
        $out = [];
        foreach ($payload as $column => $value) {
            if ($this->hasTableColumn($table, (string) $column)) {
                $out[$column] = $value;
            }
        }
        return $out;
    }

    protected function hasTableColumn(string $table, string $column): bool
    {
        if (!isset($this->columnCache[$table])) {
            if (!Schema::hasTable($table)) {
                $this->columnCache[$table] = [];
            } else {
                try {
                    $this->columnCache[$table] = Schema::getColumnListing($table);
                } catch (\Throwable $e) {
                    $this->columnCache[$table] = [];
                }
            }
        }

        if (!empty($this->columnCache[$table])) {
            return in_array($column, $this->columnCache[$table], true);
        }

        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    protected function teacherAllowedClassIds(int $teacherId, array $classIds): array
    {
        if (empty($classIds)) return [];

        // Build a set of allowed classes by checking assignments + fallback teacher_id on classes.
        $assigned = DB::table('classes as c')
            ->whereIn('c.id', $classIds)
            ->where(function ($w) use ($teacherId) {
                $w->whereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('att_teacher_class_assignments as tca')
                        ->whereColumn('tca.class_id', 'c.id')
                        ->where('tca.teacher_id', $teacherId)
                        ->where('tca.is_active', 1);
                })->orWhereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('class_teachers as ct')
                        ->whereColumn('ct.class_id', 'c.id')
                        ->where('ct.teacher_id', $teacherId)
                        ->where('ct.is_active', 1);
                })->orWhere('c.teacher_id', $teacherId);
            })
            ->pluck('c.id')
            ->map(fn ($v) => (int) $v)
            ->all();

        return $assigned;
    }

    protected function teacherAllClassIds(int $teacherId): array
    {
        // Used to constrain queries to teacher's scope without needing a separate endpoint.
        return DB::table('classes as c')
            ->where(function ($w) use ($teacherId) {
                $w->whereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('att_teacher_class_assignments as tca')
                        ->whereColumn('tca.class_id', 'c.id')
                        ->where('tca.teacher_id', $teacherId)
                        ->where('tca.is_active', 1);
                })->orWhereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('class_teachers as ct')
                        ->whereColumn('ct.class_id', 'c.id')
                        ->where('ct.teacher_id', $teacherId)
                        ->where('ct.is_active', 1);
                })->orWhere('c.teacher_id', $teacherId);
            })
            ->pluck('c.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    protected function isSessionLocked(AttSession $session): bool
    {
        if (($session->workflow_status ?? 'draft') !== 'submitted') {
            return false;
        }
        if (!$session->submitted_at) {
            return true;
        }
        return now()->greaterThan(Carbon::parse($session->submitted_at)->addDays(self::SUBMISSION_EDIT_DAYS));
    }

    protected function editableUntil(AttSession $session): ?Carbon
    {
        if (($session->workflow_status ?? 'draft') !== 'submitted') {
            return null;
        }
        if (!$session->submitted_at) {
            return null;
        }
        return Carbon::parse($session->submitted_at)->addDays(self::SUBMISSION_EDIT_DAYS);
    }
}
