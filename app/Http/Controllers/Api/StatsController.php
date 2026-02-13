<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function summary()
    {
        // Keep this endpoint extremely cheap: cache briefly and use index-friendly ranges.
        $today = now()->toDateString();
        $cacheKey = "stats:summary:{$today}";

        return Cache::remember($cacheKey, now()->addSeconds(10), function () {
            $start = now()->startOfDay();
            $end = now()->endOfDay();

            $openSessions = DB::table('att_sessions')->where('status', 'open')->count();

            $todayAttendance = DB::table('att_attendance')
                ->whereBetween('marked_at', [$start, $end])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $todaySessions = DB::table('att_sessions')
                ->whereBetween('started_at', [$start, $end])
                ->count();

            return [
                'open_sessions' => $openSessions,
                'today_sessions' => $todaySessions,
                'today_attendance' => [
                    'present' => (int) ($todayAttendance['present'] ?? 0),
                    'permission' => (int) ($todayAttendance['permission'] ?? 0),
                    'absent' => (int) ($todayAttendance['absent'] ?? 0),
                ],
            ];
        });
    }

    public function activity(Request $request)
    {
        $limit = min(100, (int)($request->query('limit', 50)));

        $cacheKey = "stats:activity:limit={$limit}";

        return Cache::remember($cacheKey, now()->addSeconds(3), function () use ($limit) {
            return DB::table('att_attendance as aa')
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
        });
    }
}
