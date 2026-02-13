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

        // Admins login by email (teachers have separate username-based login).
        $ident = (string) $data['username'];
        $isEmail = str_contains($ident, '@');
        $admin = Admin::query()
            ->where('status', 'active')
            ->when($isEmail, fn ($q) => $q->where('email', $ident))
            ->when(!$isEmail, fn ($q) => $q->where('username', $ident))
            ->first();

        $ok = false;
        if ($admin) {
            $stored = (string) $admin->getAuthPassword();
            // Prefer modern hashing (bcrypt/argon) but support legacy hashes if the mother system uses them.
            $ok = Hash::check($data['password'], $stored);
            if (!$ok) {
                if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
                    $ok = hash_equals(strtolower($stored), md5($data['password']));
                } elseif (preg_match('/^[a-f0-9]{40}$/i', $stored)) {
                    $ok = hash_equals(strtolower($stored), sha1($data['password']));
                }
            }
        }

        if (!$admin) {
            throw ValidationException::withMessages([
                'username' => [$isEmail ? 'No active admin account found for this email.' : 'No active admin account found.'],
            ]);
        }

        if (!$ok) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password.'],
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
