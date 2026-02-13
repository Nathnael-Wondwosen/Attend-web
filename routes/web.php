<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('login'))->name('login');

Route::get('/', fn () => redirect('/login'));

// Admin dashboard (no auth middleware - handled by JavaScript)
Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
Route::get('/admin/classes', [AdminController::class, 'classes'])->name('admin.classes');
Route::get('/admin/attendance', [AdminController::class, 'attendance'])->name('admin.attendance');

// All other frontend routes redirect to login (only login page is exposed)
Route::any('{any}', fn () => redirect('/login'))->where('any', '^(?!admin|api).*');
