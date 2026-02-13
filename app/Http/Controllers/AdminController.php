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
}
