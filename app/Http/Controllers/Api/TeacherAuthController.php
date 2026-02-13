<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TeacherAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $acct = AttTeacherAccount::where('username', $data['username'])
            ->where('status', 'active')
            ->first();

        if (!$acct || !Hash::check($data['password'], $acct->getAuthPassword())) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        $acct->forceFill(['last_login' => now()])->save();

        $tokenName = $data['device_name'] ?? 'teacher-mobile';
        $token = $acct->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'type' => 'teacher',
                'id' => $acct->id,
                'teacher_id' => $acct->teacher_id,
                'username' => $acct->username,
            ],
        ]);
    }
}

