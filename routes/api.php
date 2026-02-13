<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDataController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\AdvancedAnalyticsController;
use App\Http\Controllers\Api\TeacherAuthController;
use App\Http\Controllers\Api\TeacherAccountController;
use App\Http\Controllers\Api\TeacherDataController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TakeTokenController;
use App\Http\Controllers\Api\PublicTakeAttendanceController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

// Rate limits
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('scan', function (Request $request) {
    // Old behavior throttled the whole session together, which hurts real-world
    // "everyone scans at once" flows. Throttle per student per session instead,
    // with a generous per-session ceiling as a safety net.
    $sessionId = (string) ($request->route('session') ?? $request->input('session_id') ?? 'unknown');
    $studentId = (string) ($request->input('student_id') ?? 'unknown');
    $ip = (string) $request->ip();

    return [
        // Prevent a single device from spamming scans for the same session.
        Limit::perMinute(60)->by("scan:{$sessionId}:student:{$studentId}"),
        // Keep the session responsive during peak scan times; mainly a DOS guard.
        Limit::perMinute(3000)->by("scan:{$sessionId}"),
        // Basic IP guard.
        Limit::perMinute(600)->by("scan:ip:{$ip}"),
    ];
});

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('teacher/login', [TeacherAuthController::class, 'login'])->middleware('throttle:auth');

    // Public take-attendance flow (no login, but requires a secure admin-generated token).
    Route::prefix('public')->group(function () {
        Route::prefix('v1')->group(function () {
            Route::get('me', [PublicTakeAttendanceController::class, 'me']);
            Route::get('classes', [PublicTakeAttendanceController::class, 'classes']);
            Route::get('classes/{class}/sessions', [PublicTakeAttendanceController::class, 'index']);
            Route::post('classes/{class}/sessions', [PublicTakeAttendanceController::class, 'open']);
            Route::get('sessions/{session}/students', [PublicTakeAttendanceController::class, 'roster']);
            Route::patch('sessions/{session}/students', [PublicTakeAttendanceController::class, 'batchUpsertStatus']);
            Route::post('sessions/{session}/close', [PublicTakeAttendanceController::class, 'close']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Teacher: minimal endpoints for mobile app
        Route::get('teacher/classes', [TeacherDataController::class, 'classes']);

        // Admin: manage teacher login accounts (attendance owned)
        Route::get('teacher-accounts', [TeacherAccountController::class, 'index']);
        Route::get('teacher-accounts/{account}/classes', [TeacherAccountController::class, 'classes']);
        Route::post('teacher-accounts', [TeacherAccountController::class, 'store']);
        Route::patch('teacher-accounts/{account}', [TeacherAccountController::class, 'update']);

        // Admin: generate take-attendance public tokens/links (no login required for the teacher)
        Route::post('take-tokens', [TakeTokenController::class, 'create']);

        // lookups
        Route::post('classes/{class}/sessions', [SessionController::class, 'open']);
        Route::get('classes/{class}/sessions', [SessionController::class, 'index']);
        Route::get('classes/{class}/students', [AdminDataController::class, 'studentsByClass']);
        Route::get('classes', [AdminDataController::class, 'classes']);
        Route::get('teachers', [AdminDataController::class, 'teachers']);

        Route::get('sessions/{session}', [SessionController::class, 'show']);
        Route::delete('sessions/{session}', [SessionController::class, 'destroy']);
        Route::post('sessions/{session}/rotate-token', [SessionController::class, 'rotateToken']);
        Route::post('sessions/{session}/close', [SessionController::class, 'close']);
        Route::post('sessions/{session}/scan', [SessionController::class, 'scan'])->middleware('throttle:scan');
        Route::get('sessions/{session}/students', [SessionController::class, 'roster']);
        // Live updates for admin UI (SSE).
        Route::get('sessions/{session}/stream', [SessionController::class, 'stream']);
        // CSV export for a session roster (requires auth header; download via fetch in UI).
        Route::get('sessions/{session}/export', [SessionController::class, 'exportCsv']);
        // Batch status updates (1 request instead of N PATCHes)
        Route::patch('sessions/{session}/students', [SessionController::class, 'batchUpsertStatus']);
        Route::patch('sessions/{session}/students/{student}', [SessionController::class, 'upsertStatus']);

        // student (safe) detail + attendance history for admin/teacher UIs
        Route::get('students', [StudentController::class, 'index']);
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::get('students/{student}/attendance', [StudentController::class, 'attendance']);

        Route::patch('attendance/{attendance}', [SessionController::class, 'override']);

        // stats & activity
        Route::get('stats/summary', [StatsController::class, 'summary']);
        Route::get('activity', [StatsController::class, 'activity']);

        // reports
        Route::get('reports/class/{class}/day', [ReportController::class, 'classDay']);
        Route::get('reports/class/{class}/range', [ReportController::class, 'classRange']);
        Route::get('reports/class/{class}/trend', [ReportController::class, 'classTrend']);
        
        // advanced analytics
        Route::get('analytics/dashboard', [AdvancedAnalyticsController::class, 'dashboard']);
        Route::get('analytics/class/{class}', [AdvancedAnalyticsController::class, 'classAnalytics']);
        Route::get('analytics/student/{student}', [AdvancedAnalyticsController::class, 'studentProfile']);
        Route::post('analytics/predict', [AdvancedAnalyticsController::class, 'predictAttendance']);
        Route::get('analytics/anomalies', [AdvancedAnalyticsController::class, 'getAnomalies']);
    });
});
