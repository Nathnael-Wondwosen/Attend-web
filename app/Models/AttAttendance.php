<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttAttendance extends Model
{
    protected $table = 'att_attendance';

    protected $fillable = [
        'session_id',
        'class_id',
        'student_id',
        'status',
        'method',
        'marked_by',
        'marked_at',
        'note',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AttSession::class, 'session_id');
    }
}
