<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttAttendance;
use App\Models\AttSession;
use App\Models\AttSessionToken;
use App\Models\AttTeacherAccount;
use App\Models\Admin;
use App\Models\ClassTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionController extends Controller
{
    protected const STATUSES = ['present', 'absent', 'permission'];
    protected const METHODS = ['qr', 'manual'];
    protected const SUBMISSION_EDIT_DAYS = 7;
    protected const AUDIT_ACTION_SAVE_BATCH = 'attendance.save_batch';
    protected const AUDIT_ACTION_SAVE_SINGLE = 'attendance.save_single';
    protected const AUDIT_ACTION_SUBMIT = 'attendance.submit';
    protected const AUDIT_ACTION_SESSION_OPEN = 'attendance.session_open';

    public function index(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'workflow_status' => ['nullable', Rule::in(['draft', 'submitted'])],
        ]);

        $query = AttSession::where('class_id', $classId)
            ->orderByDesc('attendance_date')
            ->orderByDesc('started_at');

        if ($request->filled('from')) {
            $query->where('attendance_date', '>=', $request->date('from')->toDateString());
        }
        if ($request->filled('to')) {
            $query->where('attendance_date', '<=', $request->date('to')->toDateString());
        }
        if ($request->filled('workflow_status')) {
            $query->where('workflow_status', $request->string('workflow_status')->toString());
        }

        return $query->paginate(20);
    }

    public function show(int $id)
    {
        $session = AttSession::withCount([
            'attendance as present_count' => fn($q) => $q->where('status', 'present'),
            'attendance as permission_count' => fn($q) => $q->where('status', 'permission'),
            'attendance as absent_count' => fn($q) => $q->where('status', 'absent'),
        ])->findOrFail($id);

        $this->authorizeUserForClass(request(), (int) $session->class_id);

        return $session;
    }

    public function destroy(Request $request, int $sessionId)
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $session = AttSession::findOrFail($sessionId);

        DB::transaction(function () use ($sessionId) {
            // Delete children first (works even without FK constraints).
            DB::table('att_attendance')->where('session_id', $sessionId)->delete();
            DB::table('att_audit_logs')->where('session_id', $sessionId)->delete();
            DB::table('att_session_tokens')->where('session_id', $sessionId)->delete();
            DB::table('att_sessions')->where('id', $sessionId)->delete();
        });

        return response()->json([
            'message' => 'deleted',
            'session_id' => (int) $session->id,
            'class_id' => (int) $session->class_id,
            'attendance_date' => optional($session->attendance_date)->toDateString(),
        ]);
    }

    public function open(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $data = $request->validate([
            // Allow admin/system to open a session without a teacher id.
            'teacher_id' => ['nullable', 'integer'],
            'attendance_date' => ['required', 'date'],
            'academic_year' => ['nullable', 'integer'],
            'term' => ['nullable', Rule::in(['1st', '2nd'])],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        if ($user instanceof AttTeacherAccount) {
            // Teacher accounts may only open attendance for their assigned class.
            $data['teacher_id'] = (int) $user->teacher_id;
        }

        if (!empty($data['teacher_id'])) {
            $this->authorizeTeacherForClass((int) $data['teacher_id'], $classId);
        }

        $attendanceDate = \Illuminate\Support\Carbon::parse($data['attendance_date'])->toDateString();

        // Daily workflow: one session per (class, date). Return existing draft if found,
        // prevent duplicates on submitted sessions.
        $existing = AttSession::where('class_id', $classId)
            ->where('attendance_date', $attendanceDate)
            ->first();

        if ($existing) {
            return response()->json([
                'session' => $existing,
                'locked' => $this->isSessionLocked($existing),
                'editable_until' => $this->editableUntil($existing)?->toISOString(),
            ], 200);
        }

        $session = AttSession::create([
            'class_id' => $classId,
            'attendance_date' => $attendanceDate,
            'academic_year' => $data['academic_year'] ?? null,
            'term' => $data['term'] ?? null,
            'status' => 'open',
            'workflow_status' => 'draft',
            'started_by' => $data['teacher_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'current_token' => null,
            'token_expires_at' => null,
        ]);

        $this->audit($request, self::AUDIT_ACTION_SESSION_OPEN, [
            'session_id' => $session->id,
            'class_id' => $classId,
            'attendance_date' => $attendanceDate,
            'workflow_status' => 'draft',
        ]);

        return response()->json([
            'session' => $session,
            'locked' => false,
            'editable_until' => null,
        ], 201);
    }

    public function rotateToken(Request $request, int $sessionId)
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $session = AttSession::findOrFail($sessionId);
        if ($session->status !== 'open') {
            return response()->json(['message' => 'Session not open'], 409);
        }

        $data = $request->validate([
            'ttl_seconds' => ['nullable', 'integer', 'min:15', 'max:300'],
        ]);
        $ttl = $data['ttl_seconds'] ?? 60;
        $token = $this->generateToken();

        DB::transaction(function () use ($session, $token, $ttl) {
            $session->update([
                'current_token' => $token,
                'token_expires_at' => now()->addSeconds($ttl),
            ]);
            AttSessionToken::create([
                'session_id' => $session->id,
                'token' => $token,
                'expires_at' => $session->token_expires_at,
            ]);
        });

        return [
            'token' => $token,
            'token_expires_at' => $session->fresh()->token_expires_at,
        ];
    }

    public function close(int $sessionId)
    {
        $session = AttSession::findOrFail($sessionId);
        $this->authorizeUserForClass(request(), (int) $session->class_id);

        if (($session->workflow_status ?? 'draft') === 'submitted') {
            return response()->json([
                'message' => 'Already submitted',
                'locked' => $this->isSessionLocked($session),
                'editable_until' => $this->editableUntil($session)?->toISOString(),
            ], 200);
        }

        $classId = (int) $session->class_id;

        // On submit, ensure every active enrolled student has an entry.
        // Missing entries are recorded as absent (school can override later if needed).
        $memberIds = DB::table('class_enrollments')
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->map(fn($v) => (int) $v)
            ->all();

        if (!empty($memberIds)) {
            $already = DB::table('att_attendance')
                ->where('session_id', $sessionId)
                ->whereIn('student_id', $memberIds)
                ->pluck('student_id')
                ->map(fn($v) => (int) $v)
                ->all();

            $alreadySet = array_fill_keys($already, true);
            $now = now();
            $missing = [];
            foreach ($memberIds as $sid) {
                if (!isset($alreadySet[$sid])) {
                    $missing[] = [
                        'session_id' => $sessionId,
                        'class_id' => $classId,
                        'student_id' => $sid,
                        'status' => 'absent',
                        'method' => 'manual',
                        'marked_by' => null,
                        'marked_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($missing)) {
                DB::table('att_attendance')->insertOrIgnore($missing);
            }
        }

        $missingCount = isset($missing) ? count($missing) : 0;

        $session->update([
            'workflow_status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $session->started_by,
            'status' => 'closed',
            'closed_at' => now(),
            'current_token' => null,
            'token_expires_at' => null,
        ]);

        $this->audit(request(), self::AUDIT_ACTION_SUBMIT, [
            'session_id' => $sessionId,
            'class_id' => $classId,
            'attendance_date' => optional($session->attendance_date)->toDateString(),
            'missing_filled_absent' => $missingCount,
        ]);

        return ['message' => 'Attendance submitted'];
    }

    public function roster(int $sessionId)
    {
        $session = AttSession::findOrFail($sessionId);
        $this->authorizeUserForClass(request(), (int) $session->class_id);

        $classId = $session->class_id;

        $students = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->leftJoin('att_attendance as aa', function ($join) use ($sessionId) {
                $join->on('aa.student_id', '=', 'ce.student_id')
                     // Bind the session id constant so MySQL can use indexes cleanly.
                     ->where('aa.session_id', '=', $sessionId);
            })
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get([
                's.id as student_id',
                's.full_name',
                's.gender',
                'aa.id as attendance_id',
                'aa.status as attendance_status',
                'aa.method',
                'aa.marked_at',
            ]);

        return [
            'session' => [
                'id' => $session->id,
                'class_id' => $session->class_id,
                'attendance_date' => optional($session->attendance_date)->toDateString(),
                'workflow_status' => $session->workflow_status ?? 'draft',
                'submitted_at' => optional($session->submitted_at)->toISOString(),
                'locked' => $this->isSessionLocked($session),
                'editable_until' => $this->editableUntil($session)?->toISOString(),
            ],
            'session_id' => $sessionId,
            'class_id' => $classId,
            'students' => $students,
        ];
    }

    public function exportCsv(Request $request, int $sessionId)
    {
        $session = AttSession::findOrFail($sessionId);
        $this->authorizeUserForClass($request, (int) $session->class_id);

        $classId = (int) $session->class_id;
        $attendanceDate = optional($session->attendance_date)->toDateString();

        $className = DB::table('classes')->where('id', $classId)->value('name');
        $workflow = (string) ($session->workflow_status ?? 'draft');

        $rows = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->leftJoin('att_attendance as aa', function ($join) use ($sessionId) {
                $join->on('aa.student_id', '=', 'ce.student_id')
                    ->where('aa.session_id', '=', $sessionId);
            })
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get([
                's.id as student_id',
                's.full_name',
                's.gender',
                's.current_grade',
                'aa.status as attendance_status',
                'aa.method',
                'aa.marked_at',
                'aa.note',
            ]);

        $filenameSafeClass = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
        $filenameSafeDate = $attendanceDate ?: 'unknown_date';
        $filename = "attendance_{$filenameSafeClass}_{$filenameSafeDate}_session_{$sessionId}.csv";

        $response = new StreamedResponse(function () use ($rows, $sessionId, $classId, $className, $attendanceDate, $workflow) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // Header row
            fputcsv($out, [
                'attendance_date',
                'workflow_status',
                'class_id',
                'class_name',
                'session_id',
                'student_id',
                'full_name',
                'gender',
                'current_grade',
                'status',
                'method',
                'marked_at',
                'note',
            ]);

            foreach ($rows as $r) {
                $status = $r->attendance_status ?: 'absent';
                fputcsv($out, [
                    $attendanceDate,
                    $workflow,
                    $classId,
                    $className,
                    $sessionId,
                    $r->student_id,
                    $r->full_name,
                    $r->gender,
                    $r->current_grade,
                    $status,
                    $r->method,
                    $r->marked_at,
                    $r->note,
                ]);
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    public function stream(Request $request, int $sessionId)
    {
        // Server-Sent Events stream: keeps the admin UI responsive without polling bursts.
        // Note: SSE via EventSource cannot send Authorization headers. The admin UI uses
        // fetch() streaming so the Bearer token header still works.

        $session = AttSession::findOrFail($sessionId);

        $pollMs = min(5000, max(500, (int) $request->query('poll_ms', 1500)));
        $maxSeconds = min(300, max(10, (int) $request->query('max_seconds', 120)));
        $endsAt = microtime(true) + $maxSeconds;

        $response = new StreamedResponse(function () use ($sessionId, $pollMs, $endsAt) {
            // Ensure we flush to the client as we write.
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $lastHash = null;

            echo "retry: 2000\n\n";
            @flush();

            while (microtime(true) < $endsAt) {
                if (connection_aborted()) {
                    break;
                }

                $counts = DB::table('att_attendance')
                    ->where('session_id', $sessionId)
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status');

                $payload = [
                    'session_id' => $sessionId,
                    'counts' => [
                        'present' => (int) ($counts['present'] ?? 0),
                        'permission' => (int) ($counts['permission'] ?? 0),
                        'absent' => (int) ($counts['absent'] ?? 0),
                    ],
                    'ts' => now()->toISOString(),
                ];

                $hash = md5(json_encode($payload));
                if ($hash !== $lastHash) {
                    $lastHash = $hash;
                    $json = json_encode($payload);
                    echo "event: stats\n";
                    echo "data: {$json}\n\n";
                    @flush();
                }

                usleep($pollMs * 1000);
            }

            echo "event: end\n";
            echo "data: {}\n\n";
            @flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    public function upsertStatus(Request $request, int $sessionId, int $studentId)
    {
        $session = AttSession::findOrFail($sessionId);
        $this->authorizeUserForClass($request, (int) $session->class_id);

        if ($this->isSessionLocked($session)) {
            return response()->json(['message' => 'Attendance is locked (submitted more than 7 days ago)'], 423);
        }
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'note' => ['nullable', 'string', 'max:255'],
            'marked_by' => ['nullable', 'integer'],
        ]);

        $classId = (int) $session->class_id;

        $before = DB::table('att_attendance')
            ->where('session_id', $sessionId)
            ->where('student_id', $studentId)
            ->value('status');

        // Ensure student belongs to class
        $isMember = DB::table('class_enrollments')
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
        if (!$isMember) {
            return response()->json(['message' => 'Student not in class'], 422);
        }

        $attendance = AttAttendance::updateOrCreate(
            ['session_id' => $sessionId, 'student_id' => $studentId],
            [
                'class_id' => $classId,
                'status' => $data['status'],
                'method' => 'manual',
                'marked_by' => $data['marked_by'] ?? null,
                'note' => $data['note'] ?? null,
                'marked_at' => now(),
            ]
        );

        $this->audit($request, self::AUDIT_ACTION_SAVE_SINGLE, [
            'session_id' => $sessionId,
            'class_id' => $classId,
            'student_id' => $studentId,
            'attendance_id' => $attendance->id,
            'before' => $before,
            'after' => $attendance->status,
        ]);

        return [
            'attendance_id' => $attendance->id,
            'status' => $attendance->status,
        ];
    }

    public function batchUpsertStatus(Request $request, int $sessionId)
    {
        $session = AttSession::findOrFail($sessionId);
        $this->authorizeUserForClass($request, (int) $session->class_id);

        if ($this->isSessionLocked($session)) {
            return response()->json(['message' => 'Attendance is locked (submitted more than 7 days ago)'], 423);
        }

        $data = $request->validate([
            'updates' => ['required', 'array', 'min:1', 'max:500'],
            'updates.*.student_id' => ['required', 'integer'],
            'updates.*.status' => ['required', Rule::in(self::STATUSES)],
            'updates.*.note' => ['nullable', 'string', 'max:255'],
            'marked_by' => ['nullable', 'integer'],
        ]);

        $classId = (int) $session->class_id;
        $studentIds = collect($data['updates'])->pluck('student_id')->unique()->values();

        $beforeRows = DB::table('att_attendance')
            ->where('session_id', $sessionId)
            ->whereIn('student_id', $studentIds)
            ->pluck('status', 'student_id')
            ->mapWithKeys(fn($v, $k) => [(int) $k => $v])
            ->all();

        // One membership query instead of N exists() calls.
        $activeMembers = DB::table('class_enrollments')
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->map(fn($v) => (int) $v);

        $activeSet = array_fill_keys($activeMembers->all(), true);
        $invalid = $studentIds->filter(fn($id) => !isset($activeSet[(int) $id]))->values();
        if ($invalid->isNotEmpty()) {
            return response()->json([
                'message' => 'Some students are not in this class',
                'invalid_student_ids' => $invalid->take(25)->all(),
            ], 422);
        }

        $now = now();
        $markedBy = $data['marked_by'] ?? null;

        $rows = collect($data['updates'])->map(function ($u) use ($sessionId, $classId, $now, $markedBy) {
            return [
                'session_id' => $sessionId,
                'class_id' => $classId,
                'student_id' => (int) $u['student_id'],
                'status' => $u['status'],
                'method' => 'manual',
                'marked_by' => $markedBy,
                'note' => $u['note'] ?? null,
                'marked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('att_attendance')->upsert(
            $rows,
            ['session_id', 'student_id'],
            ['class_id', 'status', 'method', 'marked_by', 'note', 'marked_at', 'updated_at']
        );

        $changes = [];
        foreach ($data['updates'] as $u) {
            $sid = (int) $u['student_id'];
            $after = (string) $u['status'];
            $before = $beforeRows[$sid] ?? null;
            if ($before !== $after) {
                $changes[] = ['student_id' => $sid, 'before' => $before, 'after' => $after];
            }
        }

        $this->audit($request, self::AUDIT_ACTION_SAVE_BATCH, [
            'session_id' => $sessionId,
            'class_id' => $classId,
            'updates' => count($rows),
            'changed' => count($changes),
            // Keep payload bounded.
            'sample_changes' => array_slice($changes, 0, 25),
        ]);

        return response()->json([
            'updated' => count($rows),
        ]);
    }

    public function scan(Request $request, int $sessionId)
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $session = AttSession::findOrFail($sessionId);
        if ($session->status !== 'open') {
            return response()->json(['message' => 'Session closed'], 409);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'size:16'],
            'student_id' => ['required', 'integer'],
            'method' => ['nullable', Rule::in(self::METHODS)],
        ]);

        if ($data['token'] !== $session->current_token || now()->greaterThan($session->token_expires_at)) {
            return response()->json(['message' => 'Token invalid or expired'], 401);
        }

        $classId = (int) $session->class_id;

        // Validate student belongs to class and active
        $isMember = DB::table('class_enrollments')
            ->where('class_id', $classId)
            ->where('student_id', (int) $data['student_id'])
            ->where('status', 'active')
            ->exists();
        if (!$isMember) {
            return response()->json(['message' => 'Student not in class'], 422);
        }

        $method = $data['method'] ?? 'qr';
        $now = now();

        // Avoid exception-driven control flow under load: insert-or-ignore for duplicates.
        $inserted = DB::table('att_attendance')->insertOrIgnore([
            'session_id' => $session->id,
            'class_id' => $classId,
            'student_id' => (int) $data['student_id'],
            'status' => 'present',
            'method' => $method,
            'marked_by' => null,
            'marked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted
            ? ['status' => 'marked']
            : ['status' => 'already_marked'];
    }

    public function override(Request $request, int $attendanceId)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'note' => ['nullable', 'string', 'max:255'],
            'marked_by' => ['nullable', 'integer'],
        ]);

        $attendance = AttAttendance::findOrFail($attendanceId);
        $session = AttSession::find($attendance->session_id);
        if ($session) {
            $this->authorizeUserForClass($request, (int) $session->class_id);
        }
        if ($session && $this->isSessionLocked($session)) {
            return response()->json(['message' => 'Attendance is locked (submitted more than 7 days ago)'], 423);
        }
        $attendance->update([
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'marked_by' => $data['marked_by'] ?? null,
            'method' => 'manual',
            'marked_at' => now(),
        ]);

        return ['message' => 'updated'];
    }

    protected function isSessionLocked(AttSession $session): bool
    {
        if (($session->workflow_status ?? 'draft') !== 'submitted') {
            return false;
        }

        // If submitted but timestamp is missing, treat as locked for safety.
        if (!$session->submitted_at) {
            return true;
        }

        return now()->greaterThan($session->submitted_at->copy()->addDays(self::SUBMISSION_EDIT_DAYS));
    }

    protected function editableUntil(AttSession $session): ?\Illuminate\Support\Carbon
    {
        if (($session->workflow_status ?? 'draft') !== 'submitted') {
            return null;
        }
        if (!$session->submitted_at) {
            return null;
        }
        return $session->submitted_at->copy()->addDays(self::SUBMISSION_EDIT_DAYS);
    }

    protected function authorizeTeacherForClass(int $teacherId, int $classId): void
    {
        // Attendance-owned assignment table (does not touch the master roster data).
        $assigned = DB::table('att_teacher_class_assignments')
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('is_active', 1)
            ->exists()
            // Backwards-compatible roster sources (read-only usage).
            || ClassTeacher::where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('is_active', 1)
            ->exists()
            || DB::table('classes')->where('id', $classId)->where('teacher_id', $teacherId)->exists();
        if (!$assigned) {
            abort(403, 'Teacher not assigned to this class');
        }
    }

    protected function authorizeUserForClass(Request $request, int $classId): void
    {
        $user = $request->user();
        if ($user instanceof Admin) {
            return;
        }
        if ($user instanceof AttTeacherAccount) {
            $this->authorizeTeacherForClass((int) $user->teacher_id, $classId);
            return;
        }

        abort(403, 'Forbidden');
    }

    protected function generateToken(): string
    {
        return Str::upper(Str::random(16));
    }

    protected function audit(Request $request, string $action, array $meta): void
    {
        try {
            $user = $request->user();
            $actorType = 'system';
            $actorId = null;
            if ($user instanceof Admin) {
                $actorType = 'admin';
                $actorId = (int) $user->id;
            } elseif ($user instanceof AttTeacherAccount) {
                $actorType = 'teacher';
                $actorId = (int) $user->id;
            }

            DB::table('att_audit_logs')->insert([
                'session_id' => $meta['session_id'] ?? null,
                'class_id' => $meta['class_id'] ?? null,
                'student_id' => $meta['student_id'] ?? null,
                'attendance_id' => $meta['attendance_id'] ?? null,
                'action' => $action,
                'meta' => json_encode($meta),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit must never break attendance operations.
        }
    }
}
