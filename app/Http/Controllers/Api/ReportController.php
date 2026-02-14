<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttTeacherAccount;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function classDay(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $date = $request->query('date', now()->toDateString());
        $format = $request->query('format', 'json');

        $day = Carbon::parse($date)->toDateString();

        $session = DB::table('att_sessions')
            ->where('class_id', $classId)
            ->where('attendance_date', $day)
            ->orderByDesc('id')
            ->first(['id', 'workflow_status', 'submitted_at']);

        $sessionId = $session?->id;
        $workflow = (string) ($session?->workflow_status ?? 'none');

        // Base on roster to avoid missing students in draft sessions.
        // For submitted sessions, missing marks are treated as absent.
        // For drafts, missing marks are treated as unmarked.
        $rows = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->leftJoin('att_attendance as aa', function ($join) use ($sessionId) {
                $join->on('aa.student_id', '=', 'ce.student_id')
                    ->where('aa.session_id', '=', $sessionId ?: 0);
            })
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get([
                's.id as student_id',
                's.full_name',
                'aa.status',
                'aa.method',
                'aa.marked_at',
                'aa.note',
            ])
            ->map(function ($r) use ($workflow) {
                $status = $r->status;
                if (!$status) {
                    $status = ($workflow === 'submitted') ? 'absent' : 'unmarked';
                }
                return [
                    'student_id' => (int) $r->student_id,
                    'full_name' => $r->full_name,
                    'status' => $status,
                    'method' => $r->method,
                    'marked_at' => $r->marked_at,
                    'note' => $r->note,
                ];
            })
            ->values();

        if ($format === 'csv') {
            $className = DB::table('classes')->where('id', $classId)->value('name');
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_daily_{$fileBase}_{$day}.csv";

            $response = new StreamedResponse(function () use ($rows, $classId, $day, $sessionId, $workflow, $className) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, ['attendance_date', 'class_id', 'class_name', 'session_id', 'workflow_status', 'student_id', 'full_name', 'status', 'method', 'marked_at', 'note']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $day,
                        $classId,
                        $className,
                        $sessionId,
                        $workflow,
                        $row['student_id'],
                        $row['full_name'],
                        $row['status'],
                        $row['method'],
                        $row['marked_at'],
                        $row['note'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        $counts = [
            'present' => (int) $rows->where('status', 'present')->count(),
            'permission' => (int) $rows->where('status', 'permission')->count(),
            'absent' => (int) $rows->where('status', 'absent')->count(),
            'unmarked' => (int) $rows->where('status', 'unmarked')->count(),
            'total' => (int) $rows->count(),
        ];

        return response()->json([
            'class_id' => $classId,
            'date' => $day,
            'session' => $session ? [
                'id' => (int) $session->id,
                'workflow_status' => (string) ($session->workflow_status ?? 'draft'),
                'submitted_at' => $session->submitted_at ? Carbon::parse($session->submitted_at)->toISOString() : null,
            ] : null,
            'counts' => $counts,
            'rows' => $rows,
        ]);
    }

    public function classRange(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'format' => ['nullable', 'string'],
        ]);

        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $format = (string) ($data['format'] ?? $request->query('format', 'json'));

        $sessions = DB::table('att_sessions')
            ->where('class_id', $classId)
            ->whereBetween('attendance_date', [$from, $to])
            ->select(['id', 'attendance_date', 'workflow_status'])
            ->orderBy('attendance_date')
            ->get();

        $sessionIds = $sessions->pluck('id')->map(fn ($v) => (int) $v)->all();
        $hasSubmitted = $sessions->contains(fn ($s) => (string) ($s->workflow_status ?? '') === 'submitted');

        // Preload attendance marks in range.
        $marks = [];
        if (!empty($sessionIds)) {
            $marks = DB::table('att_attendance as aa')
                ->join('att_sessions as ses', 'ses.id', '=', 'aa.session_id')
                ->where('ses.class_id', $classId)
                ->whereBetween('ses.attendance_date', [$from, $to])
                ->get([
                    'aa.student_id',
                    'aa.status',
                    'ses.attendance_date',
                    'ses.workflow_status',
                ])
                ->groupBy('student_id');
        }

        $students = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get(['s.id as student_id', 's.full_name']);

        $sessionCount = (int) $sessions->count();

        $perStudent = $students->map(function ($s) use ($marks, $sessions, $sessionCount) {
            $sid = (int) $s->student_id;
            $present = 0;
            $absent = 0;
            $permission = 0;
            $unmarked = 0;

            // Build a quick lookup of attendance by date for this student.
            $byDate = [];
            if (isset($marks[$sid])) {
                foreach ($marks[$sid] as $m) {
                    $byDate[(string) $m->attendance_date] = [
                        'status' => (string) $m->status,
                        'workflow_status' => (string) ($m->workflow_status ?? 'draft'),
                    ];
                }
            }

            foreach ($sessions as $ses) {
                $d = (string) $ses->attendance_date;
                $wf = (string) ($ses->workflow_status ?? 'draft');
                $st = $byDate[$d]['status'] ?? null;
                if (!$st) {
                    $st = ($wf === 'submitted') ? 'absent' : 'unmarked';
                }
                if ($st === 'present') $present++;
                elseif ($st === 'permission') $permission++;
                elseif ($st === 'absent') $absent++;
                else $unmarked++;
            }

            $total = $sessionCount;
            $rate = $total > 0 ? round(($present * 100.0) / $total, 1) : null;

            return [
                'student_id' => $sid,
                'full_name' => $s->full_name,
                'present' => $present,
                'permission' => $permission,
                'absent' => $absent,
                'unmarked' => $unmarked,
                'total_days' => $total,
                'present_rate' => $rate,
            ];
        })->values();

        if ($format === 'csv') {
            $className = DB::table('classes')->where('id', $classId)->value('name');
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_range_{$fileBase}_{$from}_to_{$to}.csv";

            $response = new StreamedResponse(function () use ($perStudent, $classId, $className, $from, $to, $sessionCount) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, ['from', 'to', 'class_id', 'class_name', 'sessions', 'student_id', 'full_name', 'present', 'permission', 'absent', 'unmarked', 'total_days', 'present_rate']);
                foreach ($perStudent as $row) {
                    fputcsv($out, [
                        $from,
                        $to,
                        $classId,
                        $className,
                        $sessionCount,
                        $row['student_id'],
                        $row['full_name'],
                        $row['present'],
                        $row['permission'],
                        $row['absent'],
                        $row['unmarked'],
                        $row['total_days'],
                        $row['present_rate'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        $totals = [
            'present' => (int) $perStudent->sum('present'),
            'permission' => (int) $perStudent->sum('permission'),
            'absent' => (int) $perStudent->sum('absent'),
            'unmarked' => (int) $perStudent->sum('unmarked'),
            'total' => (int) $perStudent->sum('total_days'),
        ];

        return response()->json([
            'class_id' => $classId,
            'from' => $from,
            'to' => $to,
            'sessions' => [
                'count' => $sessionCount,
                'submitted_any' => $hasSubmitted,
            ],
            'totals' => $totals,
            'rows' => $perStudent,
        ]);
    }

    public function classTrend(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'format' => ['nullable', 'string'],
        ]);

        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $format = (string) ($data['format'] ?? $request->query('format', 'json'));

        // Trend by day: count statuses on submitted sessions by default;
        // draft sessions are included but missing are treated as unmarked.
        $sessions = DB::table('att_sessions')
            ->where('class_id', $classId)
            ->whereBetween('attendance_date', [$from, $to])
            ->select(['id', 'attendance_date', 'workflow_status'])
            ->orderBy('attendance_date')
            ->get();

        $sessionIds = $sessions->pluck('id')->map(fn ($v) => (int) $v)->all();

        $rosterCount = (int) DB::table('class_enrollments')
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->count();

        $byDay = [];
        foreach ($sessions as $s) {
            $byDay[(string) $s->attendance_date] = [
                'date' => (string) $s->attendance_date,
                'workflow_status' => (string) ($s->workflow_status ?? 'draft'),
                'present' => 0,
                'permission' => 0,
                'absent' => 0,
                'unmarked' => $rosterCount,
                'total' => $rosterCount,
            ];
        }

        if (!empty($sessionIds)) {
            $rows = DB::table('att_attendance as aa')
                ->join('att_sessions as ses', 'ses.id', '=', 'aa.session_id')
                ->where('ses.class_id', $classId)
                ->whereBetween('ses.attendance_date', [$from, $to])
                ->selectRaw('ses.attendance_date as d, ses.workflow_status as wf, aa.status as st, COUNT(*) as c')
                ->groupBy('ses.attendance_date', 'ses.workflow_status', 'aa.status')
                ->get();

            foreach ($rows as $r) {
                $d = (string) $r->d;
                if (!isset($byDay[$d])) continue;
                $st = (string) $r->st;
                $c = (int) $r->c;
                if ($st === 'present') $byDay[$d]['present'] += $c;
                elseif ($st === 'permission') $byDay[$d]['permission'] += $c;
                elseif ($st === 'absent') $byDay[$d]['absent'] += $c;
                // unmarked is computed below
            }

            foreach ($byDay as $d => &$v) {
                $marked = $v['present'] + $v['permission'] + $v['absent'];
                $v['unmarked'] = max(0, $rosterCount - $marked);
                if ($v['workflow_status'] === 'submitted') {
                    // Submitted sessions treat unmarked as absent.
                    $v['absent'] += $v['unmarked'];
                    $v['unmarked'] = 0;
                }
                $v['present_rate'] = $v['total'] > 0 ? round(($v['present'] * 100.0) / $v['total'], 1) : null;
            }
            unset($v);
        }

        $trend = array_values($byDay);

        if ($format === 'csv') {
            $className = DB::table('classes')->where('id', $classId)->value('name');
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_trend_{$fileBase}_{$from}_to_{$to}.csv";

            $response = new StreamedResponse(function () use ($trend, $classId, $className, $from, $to) {
                $out = fopen('php://output', 'w');
                if ($out === false) return;
                fputcsv($out, ['from', 'to', 'class_id', 'class_name', 'date', 'workflow_status', 'present', 'permission', 'absent', 'unmarked', 'total', 'present_rate']);
                foreach ($trend as $row) {
                    fputcsv($out, [
                        $from,
                        $to,
                        $classId,
                        $className,
                        $row['date'],
                        $row['workflow_status'],
                        $row['present'],
                        $row['permission'],
                        $row['absent'],
                        $row['unmarked'],
                        $row['total'],
                        $row['present_rate'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        return response()->json([
            'class_id' => $classId,
            'from' => $from,
            'to' => $to,
            'roster_count' => $rosterCount,
            'days' => $trend,
        ]);
    }

    public function studentDetail(Request $request, int $studentId)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'format' => ['nullable', 'string'],
        ]);

        $classId = (int) $data['class_id'];
        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $format = (string) ($data['format'] ?? $request->query('format', 'json'));

        // Authorization:
        // - Admin can view any student's report.
        // - Teacher account can only view if assigned to class and student is in the class roster.
        $this->authorizeUserForStudentInClass($request, $studentId, $classId);

        $student = DB::table('students')
            ->where('id', $studentId)
            ->first(['id', 'full_name', 'gender', 'current_grade']);

        if (!$student) {
            abort(404, 'Student not found');
        }

        $className = DB::table('classes')->where('id', $classId)->value('name');

        // Per-session detail (including "missing" marks as unmarked/absent depending on workflow).
        $rows = DB::table('att_sessions as ses')
            ->leftJoin('att_attendance as aa', function ($join) use ($studentId) {
                $join->on('aa.session_id', '=', 'ses.id')
                    ->where('aa.student_id', '=', $studentId);
            })
            ->where('ses.class_id', $classId)
            ->whereBetween('ses.attendance_date', [$from, $to])
            ->orderBy('ses.attendance_date')
            ->orderBy('ses.id')
            ->get([
                'ses.id as session_id',
                'ses.attendance_date',
                'ses.workflow_status',
                'ses.submitted_at',
                'aa.status',
                'aa.method',
                'aa.marked_at',
                'aa.note',
            ])
            ->map(function ($r) {
                $wf = (string) ($r->workflow_status ?? 'draft');
                $status = $r->status;
                if (!$status) {
                    $status = ($wf === 'submitted') ? 'absent' : 'unmarked';
                }
                return [
                    'session_id' => (int) $r->session_id,
                    'attendance_date' => (string) $r->attendance_date,
                    'workflow_status' => $wf,
                    'submitted_at' => $r->submitted_at ? Carbon::parse($r->submitted_at)->toISOString() : null,
                    'status' => (string) $status,
                    'method' => $r->method,
                    'marked_at' => $r->marked_at ? Carbon::parse($r->marked_at)->toISOString() : null,
                    'note' => $r->note,
                ];
            })
            ->values();

        $counts = [
            'present' => (int) $rows->where('status', 'present')->count(),
            'permission' => (int) $rows->where('status', 'permission')->count(),
            'absent' => (int) $rows->where('status', 'absent')->count(),
            'unmarked' => (int) $rows->where('status', 'unmarked')->count(),
            'total' => (int) $rows->count(),
        ];
        $presentRate = $counts['total'] > 0 ? round(($counts['present'] * 100.0) / $counts['total'], 1) : null;

        if ($format === 'csv') {
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($student->full_name ?: "student_{$studentId}"));
            $classBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_student_{$fileBase}_{$classBase}_{$from}_to_{$to}.csv";

            $response = new StreamedResponse(function () use ($rows, $student, $classId, $className, $from, $to) {
                $out = fopen('php://output', 'w');
                if ($out === false) return;
                fputcsv($out, [
                    'from',
                    'to',
                    'class_id',
                    'class_name',
                    'student_id',
                    'full_name',
                    'session_id',
                    'attendance_date',
                    'workflow_status',
                    'status',
                    'method',
                    'marked_at',
                    'note',
                ]);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $from,
                        $to,
                        $classId,
                        $className,
                        (int) $student->id,
                        (string) $student->full_name,
                        $row['session_id'],
                        $row['attendance_date'],
                        $row['workflow_status'],
                        $row['status'],
                        $row['method'],
                        $row['marked_at'],
                        $row['note'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        return response()->json([
            'class_id' => $classId,
            'class_name' => $className,
            'student' => [
                'id' => (int) $student->id,
                'full_name' => (string) $student->full_name,
                'gender' => $student->gender,
                'current_grade' => $student->current_grade,
            ],
            'from' => $from,
            'to' => $to,
            'counts' => $counts,
            'present_rate' => $presentRate,
            'rows' => $rows,
        ]);
    }

    protected function authorizeUserForClass(Request $request, int $classId): void
    {
        $user = $request->user();
        if ($user instanceof Admin) {
            return;
        }
        if ($user instanceof AttTeacherAccount) {
            $teacherId = (int) $user->teacher_id;

            $assigned = DB::table('att_teacher_class_assignments')
                ->where('class_id', $classId)
                ->where('teacher_id', $teacherId)
                ->where('is_active', 1)
                ->exists()
                || DB::table('class_teachers')
                    ->where('class_id', $classId)
                    ->where('teacher_id', $teacherId)
                    ->where('is_active', 1)
                    ->exists()
                || DB::table('classes')->where('id', $classId)->where('teacher_id', $teacherId)->exists();

            if (!$assigned) {
                abort(403, 'Teacher not assigned to this class');
            }
            return;
        }

        abort(403, 'Forbidden');
    }

    protected function authorizeUserForStudentInClass(Request $request, int $studentId, int $classId): void
    {
        $user = $request->user();
        if ($user instanceof Admin) {
            return;
        }

        // Reuse class authorization for teacher accounts.
        $this->authorizeUserForClass($request, $classId);

        // Teacher must only view students in that class.
        if ($user instanceof AttTeacherAccount) {
            $member = DB::table('class_enrollments')
                ->where('class_id', $classId)
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->exists();

            if (!$member) {
                abort(403, 'Student is not in this class');
            }

            return;
        }

        abort(403, 'Forbidden');
    }
}
