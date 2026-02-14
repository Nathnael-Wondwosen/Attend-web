<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\OpsController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('login'))->name('login');
Route::get('/teacher/login', [TeacherController::class, 'login'])->name('teacher.login');

Route::get('/', fn () => redirect('/login'));

// Public: take attendance without login (requires a secure token on the page).
Route::get('/takeattendance', fn () => view('takeattendance'))->name('takeattendance');
// Support the common typo route; keep the canonical URL as /takeattendance.
Route::get('/takeattednace', fn () => redirect('/takeattendance'));

// Ops helper: run cache/link commands without terminal (protected by OPS_RUN_KEY).
Route::get('/run', [OpsController::class, 'index']);
Route::post('/run', [OpsController::class, 'run']);

// Admin dashboard (no auth middleware - handled by JavaScript)
Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
Route::get('/admin/classes', [AdminController::class, 'classes'])->name('admin.classes');
Route::get('/admin/students', [AdminController::class, 'students'])->name('admin.students');
Route::get('/admin/attendance', [AdminController::class, 'attendance'])->name('admin.attendance');
Route::get('/admin/teacher-accounts', [AdminController::class, 'teacherAccounts'])->name('admin.teacher_accounts');
Route::get('/admin/reports', [AdminController::class, 'reports'])->name('admin.reports');

// Teacher UI (mobile-first; auth handled by JavaScript + Sanctum token)
Route::get('/teacher/attendance', [TeacherController::class, 'attendance'])->name('teacher.attendance');

// All other frontend routes redirect to login (only login page is exposed)
Route::any('{any}', fn () => redirect('/login'))->where('any', '^(?!admin|api).*');
