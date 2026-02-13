<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttSession extends Model
{
    protected $table = 'att_sessions';

    protected $fillable = [
        'class_id',
        'academic_year',
        'term',
        'status',
        'started_by',
        'started_at',
        'closed_at',
        'current_token',
        'token_expires_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'token_expires_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->hasMany(AttAttendance::class, 'session_id');
    }
}
