<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\AttTeacherAccount;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminDataController extends Controller
{
    public function classes()
    {
        $user = request()->user();

        // Return an API shape the admin UI can use without client-side placeholder data.
        // We derive:
        // - teacher_name from active primary assignment (class_teachers)
        // - students_count from active class_enrollments
        // - attendance_rate and status from attendance tables
        $q = DB::table('classes as c')
            ->leftJoin('class_teachers as ct', function ($join) {
                $join->on('ct.class_id', '=', 'c.id')
                    ->where('ct.is_active', 1)
                    ->where('ct.role', 'primary');
            })
            ->leftJoin('teachers as t_primary', 't_primary.id', '=', 'ct.teacher_id')
            // Fallback: some rosters store a direct teacher_id on classes
            ->leftJoin('teachers as t_class', 't_class.id', '=', 'c.teacher_id')
            ->select([
                'c.id',
                'c.name',
                'c.grade',
                'c.section',
                DB::raw('COALESCE(t_primary.full_name, t_class.full_name) as teacher_name'),
            ])
            ->selectSub(function ($q) {
                $q->from('class_enrollments as ce')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('ce.class_id', 'c.id')
                    ->where('ce.status', 'active');
            }, 'students_count')
            ->selectSub(function ($q) {
                $q->from('att_sessions as ses')
                    ->join('att_attendance as aa', 'aa.session_id', '=', 'ses.id')
                    ->selectRaw('ROUND(SUM(CASE WHEN aa.status = \'present\' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 1)')
                    ->whereColumn('ses.class_id', 'c.id')
                    ->whereRaw('ses.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
            }, 'attendance_rate')
            ->selectRaw("IF(EXISTS(SELECT 1 FROM att_sessions s WHERE s.class_id = c.id AND s.status = 'open' LIMIT 1), 'active', 'inactive') as status")
            ->orderBy('c.grade')
            ->orderBy('c.section');

        // If authenticated as a teacher account, restrict to their assigned classes only.
        if ($user instanceof AttTeacherAccount) {
            $teacherId = (int) $user->teacher_id;
            $q->where(function ($w) use ($teacherId) {
                $w->whereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('att_teacher_class_assignments as tca')
                        ->whereColumn('tca.class_id', 'c.id')
                        ->where('tca.teacher_id', $teacherId)
                        ->where('tca.is_active', 1);
                })->orWhereExists(function ($sub) use ($teacherId) {
                    $sub->selectRaw('1')
                        ->from('class_teachers as ctt')
                        ->whereColumn('ctt.class_id', 'c.id')
                        ->where('ctt.teacher_id', $teacherId)
                        ->where('ctt.is_active', 1);
                })->orWhere('c.teacher_id', $teacherId);
            });
        }

        return $q->get();
    }

    public function teachers()
    {
        return Teacher::where('is_active', 1)->orderBy('full_name')->get(['id', 'full_name']);
    }

    public function studentsByClass(int $classId)
    {
        $user = request()->user();
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
        }

        // Active enrollments only
        $rows = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get([
                's.id',
                's.full_name',
                's.gender',
                's.current_grade',
            ]);

        return $rows;
    }
}
