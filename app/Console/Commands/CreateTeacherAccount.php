<?php

namespace App\Console\Commands;

use App\Models\AttTeacherAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTeacherAccount extends Command
{
    protected $signature = 'attendance:teacher-account
        {teacher_id : Master roster teachers.id}
        {username : Login username}
        {--password= : Optional password (if omitted, a random password is generated)}
        {--reset : Reset password if account already exists}';

    protected $description = 'Create (or reset) an attendance teacher login account (does not modify master roster tables)';

    public function handle(): int
    {
        $teacherId = (int) $this->argument('teacher_id');
        $username = (string) $this->argument('username');

        $teacher = DB::table('teachers')->where('id', $teacherId)->first();
        if (!$teacher) {
            $this->error("Teacher id {$teacherId} not found in master roster (teachers).");
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(12));
        $hash = Hash::make($password);

        $existing = AttTeacherAccount::where('teacher_id', $teacherId)
            ->orWhere('username', $username)
            ->first();

        if ($existing) {
            if (!$this->option('reset')) {
                $this->error('Account already exists (use --reset to reset password).');
                $this->line("Existing id={$existing->id} teacher_id={$existing->teacher_id} username={$existing->username}");
                return self::FAILURE;
            }

            $existing->forceFill([
                'teacher_id' => $teacherId,
                'username' => $username,
                'password_hash' => $hash,
                'status' => 'active',
            ])->save();

            $this->info("Teacher account updated: teacher_id={$teacherId} username={$username}");
        } else {
            AttTeacherAccount::create([
                'teacher_id' => $teacherId,
                'username' => $username,
                'password_hash' => $hash,
                'status' => 'active',
            ]);

            $this->info("Teacher account created: teacher_id={$teacherId} username={$username}");
        }

        // Print password so it can be handed to the teacher/admin securely.
        $this->line("Password: {$password}");
        return self::SUCCESS;
    }
}

