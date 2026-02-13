<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'full_name',
        'christian_name',
        'gender',
        'birth_date',
        'current_grade',
    ];
}
