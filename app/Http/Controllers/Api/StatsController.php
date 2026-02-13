<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function summary()
    {
        $today = now()->toDateString();

        $openSessions = DB::table('att_sessions')->where('status', 'open')->count();

        $todayAttendance = DB::table('att_attendance')
            ->whereDate('marked_at', $today)
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        $todaySessions = DB::table('att_sessions')
            ->whereDate('started_at', $today)
            ->count();

        return [
            'open_sessions' => $openSessions,
            'today_sessions' => $todaySessions,
            'today_attendance' => [
                'present' => $todayAttendance['present'] ?? 0,
                'late' => $todayAttendance['late'] ?? 0,
                'excused' => $todayAttendance['excused'] ?? 0,
                'absent' => $todayAttendance['absent'] ?? 0,
            ],
        ];
    }

    public function activity(Request $request)
    {
        $limit = min(100, (int)($request->query('limit', 50)));

        $rows = DB::table('att_attendance as aa')
            ->join('students as s', 's.id', '=', 'aa.student_id')
            ->join('att_sessions as ses', 'ses.id', '=', 'aa.session_id')
            ->join('classes as c', 'c.id', '=', 'ses.class_id')
            ->leftJoin('teachers as t', 't.id', '=', 'aa.marked_by')
            ->orderByDesc('aa.marked_at')
            ->limit($limit)
            ->get([
                'aa.id',
                'aa.status',
                'aa.method',
                'aa.marked_at',
                's.full_name as student_name',
                'c.name as class_name',
                't.full_name as marked_by_name',
                'ses.id as session_id',
            ]);

        return $rows;
    }
}
