<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TakeTokenController extends Controller
{
    public function create(Request $request)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'ttl_hours' => ['nullable', 'integer', 'min:1', 'max:168'], // up to 7 days
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $teacherId = (int) $data['teacher_id'];
        $teacher = DB::table('teachers')->where('id', $teacherId)->first(['id', 'full_name']);
        if (!$teacher) {
            return response()->json(['message' => 'Teacher not found'], 422);
        }

        $ttl = (int) ($data['ttl_hours'] ?? 24);
        $raw = Str::lower(Str::random(48)); // displayed once to admin
        $hash = hash('sha256', $raw);

        $expiresAt = now()->addHours($ttl);

        DB::table('att_take_tokens')->insert([
            'teacher_id' => $teacherId,
            'token_hash' => $hash,
            'label' => $data['label'] ?? null,
            'status' => 'active',
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = url('/takeattendance') . '?token=' . urlencode($raw);

        return response()->json([
            'teacher' => [
                'id' => $teacherId,
                'full_name' => $teacher->full_name,
            ],
            'expires_at' => $expiresAt->toISOString(),
            'token' => $raw,
            'take_url' => $url,
        ], 201);
    }

    protected function requireAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            abort(403, 'Forbidden');
        }
    }
}

