<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttSessionToken extends Model
{
    protected $table = 'att_session_tokens';

    protected $fillable = [
        'session_id',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AttSession::class, 'session_id');
    }
}
