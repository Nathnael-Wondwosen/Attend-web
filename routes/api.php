<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDataController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\AdvancedAnalyticsController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

// Rate limits
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('scan', function (Request $request) {
    $sessionId = $request->route('session') ?? $request->input('session_id') ?? $request->ip();
    return Limit::perMinute(240)->by($sessionId);
});

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        // lookups
        Route::post('classes/{class}/sessions', [SessionController::class, 'open']);
        Route::get('classes/{class}/sessions', [SessionController::class, 'index']);
        Route::get('classes/{class}/students', [AdminDataController::class, 'studentsByClass']);
        Route::get('classes', [AdminDataController::class, 'classes']);
        Route::get('teachers', [AdminDataController::class, 'teachers']);

        Route::get('sessions/{session}', [SessionController::class, 'show']);
        Route::post('sessions/{session}/rotate-token', [SessionController::class, 'rotateToken']);
        Route::post('sessions/{session}/close', [SessionController::class, 'close']);
        Route::post('sessions/{session}/scan', [SessionController::class, 'scan'])->middleware('throttle:scan');
        Route::get('sessions/{session}/students', [SessionController::class, 'roster']);
        Route::patch('sessions/{session}/students/{student}', [SessionController::class, 'upsertStatus']);

        Route::patch('attendance/{attendance}', [SessionController::class, 'override']);

        // stats & activity
        Route::get('stats/summary', [StatsController::class, 'summary']);
        Route::get('activity', [StatsController::class, 'activity']);

        // reports
        Route::get('reports/class/{class}/day', [ReportController::class, 'classDay']);
        
        // advanced analytics
        Route::get('analytics/dashboard', [AdvancedAnalyticsController::class, 'dashboard']);
        Route::get('analytics/class/{class}', [AdvancedAnalyticsController::class, 'classAnalytics']);
        Route::get('analytics/student/{student}', [AdvancedAnalyticsController::class, 'studentProfile']);
        Route::post('analytics/predict', [AdvancedAnalyticsController::class, 'predictAttendance']);
        Route::get('analytics/anomalies', [AdvancedAnalyticsController::class, 'getAnomalies']);
    });
});
