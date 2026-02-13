<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AttTeacherAccount extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'att_teacher_accounts';

    protected $fillable = [
        'teacher_id',
        'username',
        'password_hash',
        'status',
        'last_login',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'last_login' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}

