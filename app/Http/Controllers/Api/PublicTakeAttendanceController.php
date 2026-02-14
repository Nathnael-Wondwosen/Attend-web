<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicTakeAttendanceController extends Controller
{
    protected const STATUSES = ['present', 'absent', 'permission'];

    protected function openMode(): bool
    {
        // NOTE: This intentionally removes all auth for /takeattendance.
        // Set FINOT_TAKEATTENDANCE_OPEN=false to re-enable token protection.
        return (bool) env('FINOT_TAKEATTENDANCE_OPEN', true);
    }

    public function me(Request $request)
    {
        if ($this->openMode()) {
            return response()->json([
                'open' => true,
                'message' => 'Public take-attendance is enabled (no token required).',
            ]);
        }

        $token = $this->requireTakeToken($request);
        return response()->json([
            'teacher_id' => (int) $token->teacher_id,
            'expires_at' => $token->expires_at ? Carbon::parse($token->expires_at)->toISOString() : null,
            'status' => (string) $token->status,
        ]);
    }

    public function classes(Request $request)
    {
        if ($this->openMode()) {
            return DB::table('classes as c')
                ->select([
                    'c.id',
                    'c.name',
                    'c.grade',
                    'c.section',
                ])
                ->selectSub(function ($q) {
                    $q->from('class_enrollments as ce')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('ce.class_id', 'c.id')
                        ->where('ce.status', 'active');
                }, 'students_count')
                ->orderBy('c.grade')
                ->orderBy('c.section')
                ->get();
        }

        $token = $this->requireTakeToken($request);
        $teacherId = (int) $token->teacher_id;

        return DB::table('classes as c')
            ->select([
                'c.id',
                'c.name',
                'c.grade',
                'c.section',
            ])
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
    }

    public function open(Request $request, int $classId)
    {
        $teacherId = null;
        if (!$this->openMode()) {
            $token = $this->requireTakeToken($request);
            $teacherId = (int) $token->teacher_id;
            $this->authorizeTeacherForClass($teacherId, $classId);
        }

        $data = $request->validate([
            'attendance_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $attendanceDate = Carbon::parse($data['attendance_date'])->toDateString();

        $existing = AttSession::where('class_id', $classId)
            ->where('attendance_date', $attendanceDate)
            ->first();

        if ($existing) {
            if (($existing->workflow_status ?? 'draft') === 'submitted') {
                return response()->json([
                    'message' => 'Attendance already submitted for this class/date',
                    'session' => $existing,
                    'locked' => true,
                    'editable_until' => null,
                ], 409);
            }
            return response()->json([
                'session' => $existing,
                'locked' => $this->isSessionLocked($existing),
                'editable_until' => $this->editableUntil($existing)?->toISOString(),
            ], 200);
        }

        $session = AttSession::create([
            'class_id' => $classId,
            'attendance_date' => $attendanceDate,
            'academic_year' => null,
            'term' => null,
            'status' => 'open',
            'workflow_status' => 'draft',
            'started_by' => $teacherId,
            'notes' => $data['notes'] ?? null,
            'current_token' => null,
            'token_expires_at' => null,
        ]);

        return response()->json([
            'session' => $session,
            'locked' => false,
            'editable_until' => null,
        ], 201);
    }

    public function index(Request $request, int $classId)
    {
        if (!$this->openMode()) {
            $token = $this->requireTakeToken($request);
            $teacherId = (int) $token->teacher_id;
            $this->authorizeTeacherForClass($teacherId, $classId);
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
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

        return $query->paginate(20);
    }

    public function roster(Request $request, int $sessionId)
    {
        if (!$this->openMode()) {
            $token = $this->requireTakeToken($request);
            $teacherId = (int) $token->teacher_id;

            $session = AttSession::findOrFail($sessionId);
            $this->authorizeTeacherForClass($teacherId, (int) $session->class_id);
        } else {
            $session = AttSession::findOrFail($sessionId);
        }

        $classId = (int) $session->class_id;

        $students = DB::table('class_enrollments as ce')
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
                'aa.id as attendance_id',
                'aa.status as attendance_status',
                'aa.method',
                'aa.marked_at',
            ]);

        return response()->json([
            'session' => [
                'id' => (int) $session->id,
                'class_id' => (int) $session->class_id,
                'attendance_date' => optional($session->attendance_date)->toDateString(),
                'workflow_status' => (string) ($session->workflow_status ?? 'draft'),
                'submitted_at' => optional($session->submitted_at)->toISOString(),
                'locked' => $this->isSessionLocked($session),
                'editable_until' => $this->editableUntil($session)?->toISOString(),
            ],
            'students' => $students,
        ]);
    }

    public function batchUpsertStatus(Request $request, int $sessionId)
    {
        $teacherId = null;
        if (!$this->openMode()) {
            $token = $this->requireTakeToken($request);
            $teacherId = (int) $token->teacher_id;
        }

        $session = AttSession::findOrFail($sessionId);
        if (!$this->openMode()) {
            $this->authorizeTeacherForClass((int) $teacherId, (int) $session->class_id);
        }

        if ($this->isSessionLocked($session)) {
            return response()->json(['message' => 'Attendance is locked (submitted more than 7 days ago)'], 423);
        }

        $data = $request->validate([
            'updates' => ['required', 'array', 'min:1', 'max:500'],
            'updates.*.student_id' => ['required', 'integer'],
            'updates.*.status' => ['required', Rule::in(self::STATUSES)],
            'updates.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $classId = (int) $session->class_id;
        $studentIds = collect($data['updates'])->pluck('student_id')->unique()->values();

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
        $rows = collect($data['updates'])->map(function ($u) use ($sessionId, $classId, $now, $teacherId) {
            return [
                'session_id' => $sessionId,
                'class_id' => $classId,
                'student_id' => (int) $u['student_id'],
                'status' => $u['status'],
                'method' => 'manual',
                'marked_by' => $teacherId,
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

        return response()->json(['updated' => count($rows)]);
    }

    public function close(Request $request, int $sessionId)
    {
        $teacherId = null;
        if (!$this->openMode()) {
            $token = $this->requireTakeToken($request);
            $teacherId = (int) $token->teacher_id;
        }

        $session = AttSession::findOrFail($sessionId);
        if (!$this->openMode()) {
            $this->authorizeTeacherForClass((int) $teacherId, (int) $session->class_id);
        }

        if (($session->workflow_status ?? 'draft') === 'submitted') {
            return response()->json([
                'message' => 'Attendance already submitted for this class/date',
                'locked' => true,
                'editable_until' => null,
            ], 409);
        }

        $classId = (int) $session->class_id;

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
                        'marked_by' => $teacherId,
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

        $session->update([
            'workflow_status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $teacherId,
            'status' => 'closed',
            'closed_at' => now(),
            'current_token' => null,
            'token_expires_at' => null,
        ]);

        return response()->json(['message' => 'Attendance submitted']);
    }

    protected function requireTakeToken(Request $request): object
    {
        $raw = (string) ($request->header('X-Att-Take-Token') ?: $request->query('token') ?: $request->input('token'));
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) < 16) {
            abort(401, 'Missing token');
        }

        $hash = hash('sha256', $raw);

        $row = DB::table('att_take_tokens')
            ->where('token_hash', $hash)
            ->first(['id', 'teacher_id', 'status', 'expires_at']);

        if (!$row) {
            abort(401, 'Invalid token');
        }
        if ((string) $row->status !== 'active') {
            abort(403, 'Token disabled');
        }
        if ($row->expires_at && now()->greaterThan(Carbon::parse($row->expires_at))) {
            abort(401, 'Token expired');
        }

        DB::table('att_take_tokens')->where('id', (int) $row->id)->update(['last_used_at' => now(), 'updated_at' => now()]);

        return $row;
    }

    protected function authorizeTeacherForClass(int $teacherId, int $classId): void
    {
        $assigned = DB::table('att_teacher_class_assignments')
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('is_active', 1)
            ->exists()
            || DB::table('class_teachers')
                ->where('teacher_id', $teacherId)
                ->where('class_id', $classId)
                ->where('is_active', 1)
                ->exists()
            || DB::table('classes')->where('id', $classId)->where('teacher_id', $teacherId)->exists();

        if (!$assigned) {
            abort(403, 'Teacher not assigned to this class');
        }
    }

    protected function isSessionLocked(AttSession $session): bool
    {
        // In public take-attendance flow, once a session is submitted it is locked
        // to prevent "taking attendance twice" for the same class/date.
        return (($session->workflow_status ?? 'draft') === 'submitted');
    }

    protected function editableUntil(AttSession $session): ?Carbon
    {
        return null;
    }
}
