<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDataController extends Controller
{
    public function classes()
    {
        return ClassModel::orderBy('grade')->orderBy('section')->get(['id', 'name', 'grade', 'section']);
    }

    public function teachers()
    {
        return Teacher::where('is_active', 1)->orderBy('full_name')->get(['id', 'full_name']);
    }

    public function studentsByClass(int $classId)
    {
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
