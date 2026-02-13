<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetAdminPassword extends Command
{
    protected $signature = 'admin:set-password
        {username : Admin username to update}
        {password : New password to set}
        {--force : Do not ask for confirmation}';

    protected $description = 'Reset an admin password (updates admins.password_hash with bcrypt).';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = (string) $this->argument('password');

        if ($password === '' || strlen($password) < 6) {
            $this->error('Password must be at least 6 characters.');
            return self::FAILURE;
        }

        $row = DB::table('admins')->where('username', $username)->first();
        if (!$row) {
            $this->error("Admin not found for username: {$username}");
            return self::FAILURE;
        }

        $email = (string) ($row->email ?? '');
        $status = (string) ($row->status ?? '');
        $role = (string) ($row->role ?? '');

        if (!$this->option('force')) {
            if (!$this->confirm("Reset password for admin '{$username}' (email='{$email}', status='{$status}', role='{$role}')?")) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        DB::table('admins')->where('username', $username)->update([
            'password_hash' => Hash::make($password),
        ]);

        $this->info("Password updated for admin '{$username}'.");
        return self::SUCCESS;
    }
}

