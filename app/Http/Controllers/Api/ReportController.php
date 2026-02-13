<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function classDay(Request $request, int $classId)
    {
        $date = $request->query('date', now()->toDateString());
        $format = $request->query('format', 'json');

        $rows = DB::table('att_attendance as aa')
            ->join('students as s', 's.id', '=', 'aa.student_id')
            ->join('att_sessions as ses', 'ses.id', '=', 'aa.session_id')
            ->where('ses.class_id', $classId)
            ->whereDate('aa.marked_at', $date)
            ->select([
                's.id as student_id',
                's.full_name',
                'aa.status',
                'aa.method',
                'aa.marked_at',
                'ses.id as session_id',
            ])
            ->orderBy('s.full_name')
            ->get();

        if ($format === 'csv') {
            $response = new StreamedResponse(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['student_id', 'full_name', 'status', 'method', 'marked_at', 'session_id']);
                foreach ($rows as $row) {
                    fputcsv($out, [(string)$row->student_id, $row->full_name, $row->status, $row->method, $row->marked_at, $row->session_id]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="class_'.$classId.'_'.$date.'.csv"');
            return $response;
        }

        return [
            'class_id' => $classId,
            'date' => $date,
            'rows' => $rows,
        ];
    }
}
