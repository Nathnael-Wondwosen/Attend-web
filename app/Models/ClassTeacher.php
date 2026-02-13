<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassTeacher extends Model
{
    protected $table = 'class_teachers';
    public $timestamps = false;

    protected $fillable = [
        'class_id',
        'teacher_id',
        'role',
        'assigned_date',
        'is_active',
    ];
}
