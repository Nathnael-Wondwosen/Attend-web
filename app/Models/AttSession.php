<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttSession extends Model
{
    protected $table = 'att_sessions';

    protected $fillable = [
        'class_id',
        'attendance_date',
        'academic_year',
        'term',
        'status',
        'workflow_status',
        'started_by',
        'started_at',
        'closed_at',
        'submitted_at',
        'submitted_by',
        'current_token',
        'token_expires_at',
        'notes',
    ];

    protected $casts = [
        // Serialize as YYYY-MM-DD (avoid ISO timestamps like 2026-02-13T00:00:00.000000Z in the UI).
        'attendance_date' => 'date:Y-m-d',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'token_expires_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->hasMany(AttAttendance::class, 'session_id');
    }
}
