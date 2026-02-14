<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttSession;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobileSyncController extends Controller
{
    protected const STATUSES = ['present', 'absent', 'permission'];

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

            $existingSync = DB::table('att_mobile_sync')->where('client_session_id', $clientSessionId)->first();
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
                $session = AttSession::where('class_id', $classId)->where('attendance_date', $attendanceDate)->lockForUpdate()->first();

                if ($session && (($session->workflow_status ?? 'draft') === 'submitted')) {
                    $out = [
                        'client_session_id' => $clientSessionId,
                        'status' => 'conflict',
                        'code' => 409,
                        'message' => 'Attendance already submitted for this class/date',
                        'session_id' => (int) $session->id,
                    ];
                    $this->recordSync($user->id, $classId, $attendanceDate, $deviceId, $clientSessionId, $payloadHash, (int) $session->id, $out);
                    return $out;
                }

                if (!$session) {
                    $session = AttSession::create([
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
                    ]);
                }

                $updatedCount = 0;
                if (!empty($updates)) {
                    $now = now();
                    $rows = collect($updates)->map(function ($u) use ($session, $classId, $now, $user) {
                        $markedAt = isset($u['marked_at']) ? Carbon::parse($u['marked_at']) : $now;
                        return [
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
                        ];
                    })->all();

                    DB::table('att_attendance')->upsert(
                        $rows,
                        ['session_id', 'student_id'],
                        ['class_id', 'status', 'method', 'marked_by', 'note', 'marked_at', 'updated_at']
                    );
                    $updatedCount = count($rows);
                }

                if ($submit) {
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
                            DB::table('att_attendance')->insertOrIgnore($missing);
                        }
                    }

                    $session->update([
                        'workflow_status' => 'submitted',
                        'submitted_at' => now(),
                        'submitted_by' => (int) $user->teacher_id,
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);
                }

                $out = [
                    'client_session_id' => $clientSessionId,
                    'status' => 'applied',
                    'code' => 200,
                    'session_id' => (int) $session->id,
                    'workflow_status' => (string) ($session->workflow_status ?? 'draft'),
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
}

