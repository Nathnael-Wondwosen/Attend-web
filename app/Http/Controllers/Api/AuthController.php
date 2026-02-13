<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $admin = Admin::where('username', $data['username'])
            ->where('status', 'active')
            ->first();

        if (!$admin || !Hash::check($data['password'], $admin->getAuthPassword())) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        $tokenName = $data['device_name'] ?? 'api';
        $token = $admin->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'role' => $admin->role,
                'full_name' => $admin->full_name,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Admin) {
            return response()->json([
                'type' => 'admin',
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'full_name' => $user->full_name,
            ]);
        }

        if ($user instanceof AttTeacherAccount) {
            return response()->json([
                'type' => 'teacher',
                'id' => $user->id,
                'teacher_id' => $user->teacher_id,
                'username' => $user->username,
            ]);
        }

        return response()->json(['type' => 'unknown'], 200);
    }
}
