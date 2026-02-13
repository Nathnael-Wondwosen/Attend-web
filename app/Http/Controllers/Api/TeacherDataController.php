<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherDataController extends Controller
{
    public function classes(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof AttTeacherAccount) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $teacherId = (int) $user->teacher_id;

        // Minimal shape for mobile: only what's needed to pick a class.
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
}
