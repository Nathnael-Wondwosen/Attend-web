<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttAttendance;
use App\Models\AttSession;
use App\Models\AttSessionToken;
use App\Models\ClassTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    public function index(Request $request, int $classId)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AttSession::where('class_id', $classId)
            ->orderByDesc('started_at');

        if ($request->filled('from')) {
            $query->whereDate('started_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('started_at', '<=', $request->date('to'));
        }

        return $query->paginate(20);
    }

    public function show(int $id)
    {
        $session = AttSession::withCount([
            'attendance as present_count' => fn($q) => $q->where('status', 'present'),
            'attendance as late_count' => fn($q) => $q->where('status', 'late'),
            'attendance as excused_count' => fn($q) => $q->where('status', 'excused'),
            'attendance as absent_count' => fn($q) => $q->where('status', 'absent'),
        ])->findOrFail($id);

        return $session;
    }

    public function open(Request $request, int $classId)
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'academic_year' => ['nullable', 'integer'],
            'term' => ['nullable', Rule::in(['1st', '2nd'])],
            'notes' => ['nullable', 'string'],
        ]);

        $this->authorizeTeacherForClass($data['teacher_id'], $classId);

        $openExists = AttSession::where('class_id', $classId)
            ->where('status', 'open')
            ->exists();
        if ($openExists) {
            return response()->json(['message' => 'Session already open for this class'], 409);
        }

        $token = $this->generateToken();
        $ttlSeconds = 60;

        $session = DB::transaction(function () use ($classId, $data, $token, $ttlSeconds) {
            $session = AttSession::create([
                'class_id' => $classId,
                'academic_year' => $data['academic_year'] ?? null,
                'term' => $data['term'] ?? null,
                'status' => 'open',
                'started_by' => $data['teacher_id'],
                'notes' => $data['notes'] ?? null,
                'current_token' => $token,
                'token_expires_at' => now()->addSeconds($ttlSeconds),
            ]);

            AttSessionToken::create([
                'session_id' => $session->id,
                'token' => $token,
                'expires_at' => $session->token_expires_at,
            ]);

            return $session;
        });

        return response()->json([
            'session' => $session,
            'token' => $token,
            'token_expires_at' => $session->token_expires_at,
        ], 201);
    }

    public function rotateToken(Request $request, int $sessionId)
    {
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
        if ($session->status === 'closed') {
            return response()->json(['message' => 'Already closed'], 200);
        }
        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
            'current_token' => null,
            'token_expires_at' => null,
        ]);

        return ['message' => 'Session closed'];
    }

    public function roster(int $sessionId)
    {
        $session = AttSession::findOrFail($sessionId);
        $classId = $session->class_id;

        $students = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->leftJoin('att_attendance as aa', function ($join) use ($sessionId) {
                $join->on('aa.student_id', '=', 'ce.student_id')
                     ->on('aa.session_id', '=', DB::raw((int) $sessionId));
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
            'session_id' => $sessionId,
            'class_id' => $classId,
            'students' => $students,
        ];
    }

    public function upsertStatus(Request $request, int $sessionId, int $studentId)
    {
        $session = AttSession::findOrFail($sessionId);
        $data = $request->validate([
            'status' => ['required', Rule::in(['present', 'late', 'excused', 'absent'])],
            'class_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
            'marked_by' => ['nullable', 'integer'],
        ]);

        if ($session->class_id !== (int) $data['class_id']) {
            return response()->json(['message' => 'Class mismatch'], 422);
        }

        // Ensure student belongs to class
        $isMember = DB::table('class_enrollments')
            ->where('class_id', $data['class_id'])
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
        if (!$isMember) {
            return response()->json(['message' => 'Student not in class'], 422);
        }

        $attendance = AttAttendance::updateOrCreate(
            ['session_id' => $sessionId, 'student_id' => $studentId],
            [
                'class_id' => $data['class_id'],
                'status' => $data['status'],
                'method' => 'manual',
                'marked_by' => $data['marked_by'] ?? null,
                'note' => $data['note'] ?? null,
                'marked_at' => now(),
            ]
        );

        return [
            'attendance_id' => $attendance->id,
            'status' => $attendance->status,
        ];
    }

    public function scan(Request $request, int $sessionId)
    {
        $session = AttSession::findOrFail($sessionId);
        if ($session->status !== 'open') {
            return response()->json(['message' => 'Session closed'], 409);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'size:16'],
            'student_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'method' => ['nullable', Rule::in(['qr', 'manual'])],
        ]);

        if ($data['token'] !== $session->current_token || now()->greaterThan($session->token_expires_at)) {
            return response()->json(['message' => 'Token invalid or expired'], 401);
        }

        // Validate student belongs to class and active
        $isMember = DB::table('class_enrollments')
            ->where('class_id', $data['class_id'])
            ->where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->exists();
        if (!$isMember) {
            return response()->json(['message' => 'Student not in class'], 422);
        }

        $method = $data['method'] ?? 'qr';

        try {
            $attendance = AttAttendance::create([
                'session_id' => $session->id,
                'class_id' => $data['class_id'],
                'student_id' => $data['student_id'],
                'status' => 'present',
                'method' => $method,
                'marked_by' => null,
            ]);

            return [
                'status' => 'marked',
                'attendance_id' => $attendance->id,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate (unique constraint) – treat as already scanned
            if (str_contains($e->getMessage(), 'att_unique_session_student')) {
                return [
                    'status' => 'already_marked',
                ];
            }
            throw $e;
        }
    }

    public function override(Request $request, int $attendanceId)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['present', 'late', 'excused', 'absent'])],
            'note' => ['nullable', 'string', 'max:255'],
            'marked_by' => ['nullable', 'integer'],
        ]);

        $attendance = AttAttendance::findOrFail($attendanceId);
        $attendance->update([
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'marked_by' => $data['marked_by'] ?? null,
            'method' => 'manual',
            'marked_at' => now(),
        ]);

        return ['message' => 'updated'];
    }

    protected function authorizeTeacherForClass(int $teacherId, int $classId): void
    {
        $assigned = ClassTeacher::where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('is_active', 1)
            ->exists();
        if (!$assigned) {
            abort(403, 'Teacher not assigned to this class');
        }
    }

    protected function generateToken(): string
    {
        return Str::upper(Str::random(16));
    }
}
