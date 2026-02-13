<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AttTeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeacherAccountController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $rows = DB::table('att_teacher_accounts as a')
            ->join('teachers as t', 't.id', '=', 'a.teacher_id')
            ->leftJoin('att_teacher_class_assignments as tca', function ($join) {
                $join->on('tca.teacher_id', '=', 'a.teacher_id')
                    ->where('tca.is_active', 1);
            })
            ->orderBy('t.full_name')
            ->groupBy([
                'a.id',
                'a.teacher_id',
                'a.username',
                'a.status',
                'a.last_login',
                'a.created_at',
                't.full_name',
                't.is_active',
            ])
            ->get([
                'a.id',
                'a.teacher_id',
                'a.username',
                'a.status',
                'a.last_login',
                'a.created_at',
                't.full_name as teacher_name',
                't.is_active as teacher_active',
                DB::raw('COUNT(tca.class_id) as assigned_classes_count'),
            ]);

        return response()->json($rows);
    }

    public function classes(Request $request, int $accountId)
    {
        $this->requireAdmin($request);

        $acct = AttTeacherAccount::findOrFail($accountId);

        $classIds = DB::table('att_teacher_class_assignments')
            ->where('teacher_id', (int) $acct->teacher_id)
            ->where('is_active', 1)
            ->orderBy('class_id')
            ->pluck('class_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        return response()->json([
            'account_id' => (int) $acct->id,
            'teacher_id' => (int) $acct->teacher_id,
            'class_ids' => $classIds,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'username' => ['required', 'string', 'min:3', 'max:64'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'class_ids' => ['nullable', 'array', 'max:50'],
            'class_ids.*' => ['integer'],
        ]);

        $teacher = DB::table('teachers')->where('id', (int) $data['teacher_id'])->first();
        if (!$teacher) {
            return response()->json(['message' => 'Teacher not found'], 422);
        }

        $exists = AttTeacherAccount::where('username', $data['username'])->exists();
        if ($exists) {
            return response()->json(['message' => 'Username already taken'], 422);
        }

        $password = $data['password'] ?? Str::password(12);

        $acct = AttTeacherAccount::create([
            'teacher_id' => (int) $data['teacher_id'],
            'username' => $data['username'],
            'password_hash' => Hash::make($password),
            'status' => 'active',
        ]);

        if (!empty($data['class_ids'])) {
            $this->syncAssignments((int) $data['teacher_id'], $data['class_ids']);
        }

        return response()->json([
            'account' => [
                'id' => $acct->id,
                'teacher_id' => $acct->teacher_id,
                'username' => $acct->username,
                'status' => $acct->status,
            ],
            // Returned once so admin can share it to the teacher securely.
            'password' => $password,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
            'reset_password' => ['nullable', 'boolean'],
            'new_password' => ['nullable', 'string', 'min:8', 'max:128'],
            'username' => ['nullable', 'string', 'min:3', 'max:64'],
            'class_ids' => ['nullable', 'array', 'max:50'],
            'class_ids.*' => ['integer'],
        ]);

        $acct = AttTeacherAccount::findOrFail($id);

        $respPassword = null;

        if (!empty($data['username']) && $data['username'] !== $acct->username) {
            $exists = AttTeacherAccount::where('username', $data['username'])->where('id', '!=', $acct->id)->exists();
            if ($exists) {
                return response()->json(['message' => 'Username already taken'], 422);
            }
            $acct->username = $data['username'];
        }

        if (!empty($data['status'])) {
            $acct->status = $data['status'];
        }

        if (!empty($data['reset_password'])) {
            $pw = $data['new_password'] ?? Str::password(12);
            $acct->password_hash = Hash::make($pw);
            $respPassword = $pw;
        }

        $acct->save();

        if (array_key_exists('class_ids', $data)) {
            $this->syncAssignments((int) $acct->teacher_id, $data['class_ids'] ?? []);
        }

        return response()->json([
            'account' => [
                'id' => $acct->id,
                'teacher_id' => $acct->teacher_id,
                'username' => $acct->username,
                'status' => $acct->status,
                'last_login' => optional($acct->last_login)->toISOString(),
            ],
            'password' => $respPassword,
        ]);
    }

    protected function syncAssignments(int $teacherId, array $classIds): void
    {
        // Only touches attendance-owned table, not the master roster tables.
        $ids = collect($classIds)->map(fn ($v) => (int) $v)->unique()->values();

        // Validate class ids exist (avoid storing garbage).
        if ($ids->isNotEmpty()) {
            $valid = DB::table('classes')->whereIn('id', $ids)->pluck('id')->map(fn ($v) => (int) $v);
            $validSet = array_fill_keys($valid->all(), true);
            $ids = $ids->filter(fn ($id) => isset($validSet[(int) $id]))->values();
        }

        // Deactivate existing assignments not in the set (attendance-owned only).
        DB::table('att_teacher_class_assignments')
            ->where('teacher_id', $teacherId)
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereNotIn('class_id', $ids))
            ->update(['is_active' => 0, 'updated_at' => now()]);

        if ($ids->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $ids->map(fn ($cid) => [
            'teacher_id' => $teacherId,
            'class_id' => (int) $cid,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('att_teacher_class_assignments')->upsert(
            $rows,
            ['teacher_id', 'class_id'],
            ['is_active', 'updated_at']
        );
    }

    protected function requireAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            abort(403, 'Forbidden');
        }
    }
}
