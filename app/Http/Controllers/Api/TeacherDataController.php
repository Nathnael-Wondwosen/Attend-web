<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherDataController extends Controller
{
    protected function allowedClassIds(int $teacherId): array
    {
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

    public function classes(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof AttTeacherAccount) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $teacherId = (int) $user->teacher_id;

        // Minimal shape for mobile: only what's needed to pick a class.
        $classes = DB::table('classes as c')
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
            ->get()
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                    'grade' => $r->grade,
                    'section' => $r->section,
                    'students_count' => (int) ($r->students_count ?? 0),
                ];
            })
            ->values();

        return response()->json(['data' => $classes]);
    }

    public function students(Request $request, int $class)
    {
        $user = $request->user();
        if (!$user instanceof AttTeacherAccount) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $allowedSet = array_fill_keys($this->allowedClassIds((int) $user->teacher_id), true);
        if (!isset($allowedSet[(int) $class])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $parentPhones = DB::table('parents as p')
            ->selectRaw('p.student_id, MIN(p.id) as min_parent_id')
            ->whereNotNull('p.phone_number')
            ->whereRaw("TRIM(p.phone_number) <> ''")
            ->groupBy('p.student_id');

        $students = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->leftJoinSub($parentPhones, 'pp', function ($join) {
                $join->on('pp.student_id', '=', 's.id');
            })
            ->leftJoin('parents as p', 'p.id', '=', 'pp.min_parent_id')
            ->where('ce.class_id', (int) $class)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get([
                's.id',
                's.full_name',
                's.gender',
                's.current_grade',
                DB::raw("COALESCE(NULLIF(TRIM(s.phone_number), ''), NULLIF(TRIM(p.phone_number), '')) as phone_number"),
                DB::raw("CASE WHEN NULLIF(TRIM(s.phone_number), '') IS NOT NULL THEN 'student' WHEN NULLIF(TRIM(p.phone_number), '') IS NOT NULL THEN 'parent' ELSE 'none' END as phone_source"),
            ])
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'full_name' => (string) $r->full_name,
                    'gender' => $r->gender,
                    'current_grade' => $r->current_grade,
                    'phone_number' => $r->phone_number ? (string) $r->phone_number : null,
                    'phone_source' => (string) ($r->phone_source ?? 'none'),
                    'has_phone' => !empty($r->phone_number),
                ];
            })
            ->values();

        return response()->json(['data' => $students]);
    }
}
