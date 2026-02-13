<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'name',
        'grade',
        'section',
        'academic_year',
        'capacity',
        'teacher_id',
        'description',
    ];
}
