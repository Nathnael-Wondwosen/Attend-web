<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $table = 'teachers';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'qualification',
        'experience_years',
        'is_active',
    ];
}
