<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttAttendance;
use App\Models\AttSession;
use App\Models\ClassModel;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvancedAnalyticsController extends Controller
{
    /**
     * Get comprehensive attendance analytics dashboard data
     */
    public function dashboard()
    {
        $today = now()->toDateString();
        
        // Overall statistics
        $totalStudents = Student::count();
        $activeClasses = ClassModel::count();
        
        // Today's attendance rate
        $todayAttendance = $this->getTodayAttendanceRate();
        
        // Recent alerts
        $recentAlerts = $this->getRecentAlerts();
        
        // Weekly trend data
        $weeklyTrend = $this->getWeeklyAttendanceTrend();
        
        // Class performance rankings
        $classPerformance = $this->getClassPerformance();
        
        // Attendance patterns
        $attendancePatterns = $this->getAttendancePatterns();
        
        return response()->json([
            'statistics' => [
                'total_students' => $totalStudents,
                'active_classes' => $activeClasses,
                'today_attendance_rate' => $todayAttendance,
                'alerts_count' => count($recentAlerts)
            ],
            'trends' => [
                'weekly' => $weeklyTrend,
                'patterns' => $attendancePatterns
            ],
            'rankings' => [
                'class_performance' => $classPerformance
            ],
            'alerts' => $recentAlerts,
            'timestamp' => now()
        ]);
    }

    /**
     * Get detailed class analytics
     */
    public function classAnalytics($classId)
    {
        $class = ClassModel::findOrFail($classId);
        
        // Class attendance statistics
        $attendanceStats = $this->getClassAttendanceStats($classId);
        
        // Student performance within class
        $studentPerformance = $this->getClassStudentPerformance($classId);
        
        // Attendance trends for this class
        $classTrends = $this->getClassTrends($classId);
        
        // Risk assessment
        $riskAssessment = $this->getClassRiskAssessment($classId);
        
        return response()->json([
            'class' => $class,
            'statistics' => $attendanceStats,
            'student_performance' => $studentPerformance,
            'trends' => $classTrends,
            'risk_assessment' => $riskAssessment
        ]);
    }

    /**
     * Get student attendance profile with predictive analytics
     */
    public function studentProfile($studentId)
    {
        $student = Student::findOrFail($studentId);
        
        // Attendance history
        $attendanceHistory = $this->getStudentAttendanceHistory($studentId);
        
        // Attendance patterns and predictions
        $patterns = $this->getStudentPatterns($studentId);
        
        // Risk factors
        $riskFactors = $this->getStudentRiskFactors($studentId);
        
        // Recommendations
        $recommendations = $this->getStudentRecommendations($studentId, $patterns, $riskFactors);
        
        return response()->json([
            'student' => $student,
            'attendance_history' => $attendanceHistory,
            'patterns' => $patterns,
            'risk_factors' => $riskFactors,
            'recommendations' => $recommendations
        ]);
    }

    /**
     * Generate attendance predictions using simple analytics
     */
    public function predictAttendance(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'date' => 'required|date'
        ]);
        
        $classId = $request->class_id;
        $targetDate = $request->date;
        
        // Historical data analysis
        $historicalData = $this->getHistoricalClassData($classId);
        
        // Simple prediction algorithm
        $prediction = $this->calculateAttendancePrediction($historicalData, $targetDate);
        
        // Confidence level
        $confidence = $this->calculatePredictionConfidence($historicalData);
        
        return response()->json([
            'predicted_attendance_rate' => $prediction,
            'confidence_level' => $confidence,
            'factors' => [
                'historical_average' => $historicalData['average'],
                'trend_direction' => $historicalData['trend'],
                'seasonal_factor' => $historicalData['seasonal']
            ]
        ]);
    }

    /**
     * Get attendance anomalies and unusual patterns
     */
    public function getAnomalies(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
            'threshold' => 'nullable|numeric|min:0|max:1'
        ]);
        
        $days = $request->days ?? 7;
        $threshold = $request->threshold ?? 0.7;
        
        $anomalies = $this->detectAttendanceAnomalies($days, $threshold);
        
        return response()->json([
            'anomalies' => $anomalies,
            'parameters' => [
                'analysis_period_days' => $days,
                'threshold' => $threshold
            ]
        ]);
    }

    // Private helper methods
    private function getTodayAttendanceRate()
    {
        $totalSessions = AttSession::whereDate('started_at', now()->toDateString())->count();
        if ($totalSessions === 0) return 0;
        
        $attendedCount = AttAttendance::whereDate('marked_at', now()->toDateString())
            ->where('status', 'present')
            ->count();
            
        $totalCount = AttAttendance::whereDate('marked_at', now()->toDateString())->count();
        
        return $totalCount > 0 ? round(($attendedCount / $totalCount) * 100, 1) : 0;
    }

    private function getRecentAlerts()
    {
        // Students with consecutive absences
        $absentStudents = DB::select("
            SELECT s.id, s.full_name, COUNT(a.id) as absence_count
            FROM students s
            JOIN att_attendance a ON s.id = a.student_id
            WHERE a.status = 'absent' 
            AND a.marked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY s.id, s.full_name
            HAVING COUNT(a.id) >= 3
            ORDER BY absence_count DESC
            LIMIT 10
        ");
        
        // Classes with low attendance
        $lowAttendanceClasses = DB::select("
            SELECT c.id, c.name, 
                   COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*) as attendance_rate
            FROM classes c
            JOIN att_sessions s ON c.id = s.class_id
            JOIN att_attendance a ON s.id = a.session_id
            WHERE s.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY c.id, c.name
            HAVING attendance_rate < 75
            ORDER BY attendance_rate ASC
            LIMIT 5
        ");
        
        return [
            'absent_students' => $absentStudents,
            'low_attendance_classes' => $lowAttendanceClasses
        ];
    }

    private function getWeeklyAttendanceTrend()
    {
        $data = DB::select("
            SELECT DATE(a.marked_at) as date,
                   COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*) as rate
            FROM att_attendance a
            WHERE a.marked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(a.marked_at)
            ORDER BY DATE(a.marked_at)
        ");
        
        return collect($data)->map(function($item) {
            return [
                'date' => $item->date,
                'rate' => round($item->rate, 1)
            ];
        });
    }

    private function getClassPerformance()
    {
        return DB::select("
            SELECT c.id, c.name, c.grade, c.section,
                   COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*) as attendance_rate
            FROM classes c
            JOIN att_sessions s ON c.id = s.class_id
            JOIN att_attendance a ON s.id = a.session_id
            WHERE s.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY c.id, c.name, c.grade, c.section
            ORDER BY attendance_rate DESC
        ");
    }

    private function getAttendancePatterns()
    {
        // Day of week patterns
        $dayPatterns = DB::select("
            SELECT DAYNAME(a.marked_at) as day,
                   COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*) as rate
            FROM att_attendance a
            WHERE a.marked_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY DAYNAME(a.marked_at)
            ORDER BY FIELD(DAYNAME(a.marked_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')
        ");
        
        return [
            'by_day' => $dayPatterns
        ];
    }

    private function getClassAttendanceStats($classId)
    {
        return DB::selectOne(
            "SELECT 
                COUNT(*) as total_sessions,
                AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as average_attendance
            FROM att_sessions s
            JOIN att_attendance a ON s.id = a.session_id
            WHERE s.class_id = ?
            AND s.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$classId]
        );
    }

    private function getClassStudentPerformance($classId)
    {
        return DB::select(
            "SELECT 
                s.id, s.full_name,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*) as attendance_rate,
                COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count
            FROM students s
            JOIN class_enrollments ce ON s.id = ce.student_id
            JOIN att_attendance a ON s.id = a.student_id
            JOIN att_sessions ses ON a.session_id = ses.id
            WHERE ce.class_id = ?
            AND ce.status = 'active'
            AND ses.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY s.id, s.full_name
            ORDER BY attendance_rate DESC",
            [$classId]
        );
    }

    private function getClassTrends($classId)
    {
        return DB::select(
            "SELECT 
                DATE(ses.started_at) as date,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*) as rate
            FROM att_sessions ses
            JOIN att_attendance a ON ses.id = a.session_id
            WHERE ses.class_id = ?
            AND ses.started_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            GROUP BY DATE(ses.started_at)
            ORDER BY DATE(ses.started_at)",
            [$classId]
        );
    }

    private function getClassRiskAssessment($classId)
    {
        $lowAttendanceStudents = DB::select(
            "SELECT COUNT(*) as count
            FROM students s
            JOIN class_enrollments ce ON s.id = ce.student_id
            JOIN att_attendance a ON s.id = a.student_id
            JOIN att_sessions ses ON a.session_id = ses.id
            WHERE ce.class_id = ?
            AND ce.status = 'active'
            AND a.status = 'absent'
            AND ses.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY s.id
            HAVING COUNT(*) >= 5",
            [$classId]
        );

        return [
            'high_risk_students' => count($lowAttendanceStudents),
            'recommendation' => count($lowAttendanceStudents) > 0 ? 'Intervention recommended' : 'Class performing well'
        ];
    }

    private function getStudentAttendanceHistory($studentId)
    {
        return DB::select(
            "SELECT 
                ses.started_at as session_date,
                a.status,
                a.marked_at,
                c.name as class_name
            FROM att_attendance a
            JOIN att_sessions ses ON a.session_id = ses.id
            JOIN classes c ON ses.class_id = c.id
            WHERE a.student_id = ?
            ORDER BY ses.started_at DESC
            LIMIT 30",
            [$studentId]
        );
    }

    private function getStudentPatterns($studentId)
    {
        $absences = DB::select(
            "SELECT COUNT(*) as count
            FROM att_attendance
            WHERE student_id = ? AND status = 'absent'
            AND marked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$studentId]
        );

        $lates = DB::select(
            "SELECT COUNT(*) as count
            FROM att_attendance
            WHERE student_id = ? AND status = 'late'
            AND marked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$studentId]
        );

        return [
            'recent_absences' => $absences[0]->count ?? 0,
            'recent_lates' => $lates[0]->count ?? 0,
            'trend' => $absences[0]->count > 3 ? 'concerning' : 'normal'
        ];
    }

    private function getStudentRiskFactors($studentId)
    {
        $totalSessions = DB::select(
            "SELECT COUNT(*) as count
            FROM att_attendance
            WHERE student_id = ?
            AND marked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$studentId]
        );

        $presentCount = DB::select(
            "SELECT COUNT(*) as count
            FROM att_attendance
            WHERE student_id = ? AND status = 'present'
            AND marked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$studentId]
        );

        $attendanceRate = $totalSessions[0]->count > 0 
            ? ($presentCount[0]->count / $totalSessions[0]->count) * 100 
            : 0;

        return [
            'attendance_rate' => round($attendanceRate, 1),
            'risk_level' => $attendanceRate < 75 ? 'high' : ($attendanceRate < 90 ? 'medium' : 'low')
        ];
    }

    private function getStudentRecommendations($studentId, $patterns, $riskFactors)
    {
        $recommendations = [];

        if ($riskFactors['risk_level'] === 'high') {
            $recommendations[] = 'Immediate parent contact recommended';
            $recommendations[] = 'Counseling session advised';
        } elseif ($riskFactors['risk_level'] === 'medium') {
            $recommendations[] = 'Monitor attendance closely';
        }

        if ($patterns['recent_absences'] >= 3) {
            $recommendations[] = 'Investigate potential underlying issues';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Student attendance is satisfactory';
        }

        return $recommendations;
    }

    private function getHistoricalClassData($classId)
    {
        $data = DB::select(
            "SELECT 
                AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as average,
                COUNT(*) as total_records
            FROM att_sessions s
            JOIN att_attendance a ON s.id = a.session_id
            WHERE s.class_id = ?
            AND s.started_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)",
            [$classId]
        );

        return [
            'average' => $data[0]->average ?? 0,
            'trend' => 'stable',
            'seasonal' => 1.0,
            'total_records' => $data[0]->total_records ?? 0
        ];
    }

    private function calculateAttendancePrediction($historicalData, $targetDate)
    {
        // Simple prediction based on historical average
        $baseRate = $historicalData['average'];
        
        // Adjust for day of week (simplified)
        $dayAdjustment = date('N', strtotime($targetDate)) <= 5 ? 1.02 : 0.95;
        
        $predicted = $baseRate * $dayAdjustment;
        return min(100, max(0, round($predicted, 1)));
    }

    private function calculatePredictionConfidence($historicalData)
    {
        $recordCount = $historicalData['total_records'];
        
        if ($recordCount >= 50) return 0.9;
        if ($recordCount >= 20) return 0.75;
        if ($recordCount >= 10) return 0.6;
        return 0.4;
    }

    private function detectAttendanceAnomalies($days, $threshold)
    {
        return DB::select(
            "SELECT 
                c.id as class_id,
                c.name as class_name,
                DATE(ses.started_at) as date,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*) as attendance_rate,
                COUNT(*) as total_students
            FROM classes c
            JOIN att_sessions ses ON c.id = ses.class_id
            JOIN att_attendance a ON ses.id = a.session_id
            WHERE ses.started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY c.id, c.name, DATE(ses.started_at)
            HAVING attendance_rate < ?
            ORDER BY attendance_rate ASC",
            [$days, $threshold * 100]
        );
    }
}