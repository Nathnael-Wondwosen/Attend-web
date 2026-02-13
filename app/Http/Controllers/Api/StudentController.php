<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof Admin && !$user instanceof AttTeacherAccount) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'class_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));
        $classId = isset($data['class_id']) ? (int) $data['class_id'] : null;
        $limit = (int) ($data['limit'] ?? 30);
        $offset = (int) ($data['offset'] ?? 0);

        if ($classId !== null) {
            $exists = DB::table('classes')->where('id', $classId)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Class not found'], 422);
            }
        }

        $query = DB::table('students as s')
            ->select([
                's.id',
                's.full_name',
                's.gender',
                's.current_grade',
            ]);

        if ($q !== '') {
            // Support ID exact match, otherwise name search.
            if (ctype_digit($q)) {
                $query->where('s.id', (int) $q);
            } else {
                $query->where('s.full_name', 'like', "%{$q}%");
            }
        }

        // Filter to a single class roster if requested (active enrollments only).
        if ($classId !== null) {
            $query->whereExists(function ($sub) use ($classId) {
                $sub->selectRaw('1')
                    ->from('class_enrollments as ce')
                    ->whereColumn('ce.student_id', 's.id')
                    ->where('ce.class_id', $classId)
                    ->where('ce.status', 'active');
            });
        }

        // If teacher, only show students that are enrolled in their assigned classes.
        if ($user instanceof AttTeacherAccount) {
            $teacherId = (int) $user->teacher_id;

            if ($classId !== null) {
                // Teacher may only query a class they are assigned to.
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
            }

            $query->whereExists(function ($sub) use ($teacherId) {
                $sub->selectRaw('1')
                    ->from('class_enrollments as ce')
                    ->join('classes as c', 'c.id', '=', 'ce.class_id')
                    ->whereColumn('ce.student_id', 's.id')
                    ->where('ce.status', 'active')
                    ->where(function ($w) use ($teacherId) {
                        $w->whereExists(function ($sub2) use ($teacherId) {
                            $sub2->selectRaw('1')
                                ->from('att_teacher_class_assignments as tca')
                                ->whereColumn('tca.class_id', 'c.id')
                                ->where('tca.teacher_id', $teacherId)
                                ->where('tca.is_active', 1);
                        })->orWhereExists(function ($sub2) use ($teacherId) {
                            $sub2->selectRaw('1')
                                ->from('class_teachers as ct')
                                ->whereColumn('ct.class_id', 'c.id')
                                ->where('ct.teacher_id', $teacherId)
                                ->where('ct.is_active', 1);
                        })->orWhere('c.teacher_id', $teacherId);
                    });
            });
        }

        $rows = $query
            ->orderBy('s.full_name')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'q' => $q,
            'class_id' => $classId,
            'limit' => $limit,
            'offset' => $offset,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, int $studentId)
    {
        $classId = $request->query('class_id');
        $this->authorizeUserForStudent($request, $studentId, $classId !== null ? (int) $classId : null);

        $student = DB::table('students')
            ->where('id', $studentId)
            ->first([
                'id',
                'full_name',
                'gender',
                'current_grade',
            ]);

        if (!$student) {
            abort(404, 'Student not found');
        }

        return $student;
    }

    public function attendance(Request $request, int $studentId)
    {
        $data = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $classId = isset($data['class_id']) ? (int) $data['class_id'] : null;
        $this->authorizeUserForStudent($request, $studentId, $classId);

        $limit = (int) ($data['limit'] ?? 30);

        $summaryFrom = isset($data['from'])
            ? Carbon::parse($data['from'])->toDateString()
            : now()->subDays(30)->toDateString();

        $summaryTo = isset($data['to'])
            ? Carbon::parse($data['to'])->toDateString()
            : now()->toDateString();

        $base = DB::table('att_attendance as aa')
            ->join('att_sessions as ses', 'ses.id', '=', 'aa.session_id')
            ->join('classes as c', 'c.id', '=', 'ses.class_id')
            ->where('aa.student_id', $studentId);

        if ($classId !== null) {
            $base->where('ses.class_id', $classId);
        }

        $summary = (clone $base)
            ->whereBetween('ses.attendance_date', [$summaryFrom, $summaryTo])
            ->selectRaw("SUM(CASE WHEN aa.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN aa.status = 'permission' THEN 1 ELSE 0 END) as permission")
            ->selectRaw("SUM(CASE WHEN aa.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw('COUNT(*) as total')
            ->first();

        $recent = (clone $base)
            ->orderByDesc('ses.attendance_date')
            ->orderByDesc('aa.marked_at')
            ->limit($limit)
            ->get([
                'aa.id as attendance_id',
                'aa.status',
                'aa.method',
                'aa.marked_at',
                'aa.note',
                'ses.id as session_id',
                'ses.class_id',
                'ses.attendance_date',
                'ses.workflow_status',
                'ses.submitted_at',
                'c.name as class_name',
            ]);

        return response()->json([
            'student_id' => $studentId,
            'class_id' => $classId,
            'summary' => [
                'from' => $summaryFrom,
                'to' => $summaryTo,
                'present' => (int) ($summary->present ?? 0),
                'permission' => (int) ($summary->permission ?? 0),
                'absent' => (int) ($summary->absent ?? 0),
                'total' => (int) ($summary->total ?? 0),
            ],
            'recent' => $recent,
        ]);
    }

    protected function authorizeUserForStudent(Request $request, int $studentId, ?int $classId = null): void
    {
        $user = $request->user();
        if ($user instanceof Admin) {
            return;
        }

        if ($user instanceof AttTeacherAccount) {
            $teacherId = (int) $user->teacher_id;

            if ($classId !== null) {
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

            // Without a class filter, allow only if the student is enrolled in at least one class assigned to this teacher.
            $ok = DB::table('class_enrollments as ce')
                ->join('classes as c', 'c.id', '=', 'ce.class_id')
                ->where('ce.student_id', $studentId)
                ->where('ce.status', 'active')
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
                ->exists();

            if (!$ok) {
                abort(403, 'Forbidden');
            }

            return;
        }

        abort(403, 'Forbidden');
    }
}
