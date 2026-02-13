<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard');
    }

    public function classes()
    {
        return view('admin.classes');
    }

    public function attendance()
    {
        return view('admin.attendance');
    }

    public function students()
    {
        return view('admin.students');
    }

    public function teacherAccounts()
    {
        return view('admin.teacher_accounts');
    }

    public function reports()
    {
        return view('admin.reports');
    }
}
