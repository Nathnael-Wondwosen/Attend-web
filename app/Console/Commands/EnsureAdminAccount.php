<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EnsureAdminAccount extends Command
{
    protected $signature = 'admin:ensure
        {--username= : Admin username (defaults to FINOT_ADMIN_USERNAME or "admin")}
        {--email= : Admin email (defaults to FINOT_ADMIN_EMAIL)}
        {--full-name= : Admin full name (defaults to FINOT_ADMIN_FULL_NAME)}
        {--password= : Admin password (defaults to FINOT_ADMIN_PASSWORD)}
        {--role= : Admin role (defaults to FINOT_ADMIN_ROLE or "super_admin")}
        {--status= : Admin status (defaults to FINOT_ADMIN_STATUS or "active")}
        {--update-existing : Update existing admin fields if account already exists}';

    protected $description = 'Ensure an admin account exists for login on a new/empty database.';

    public function handle(): int
    {
        if (!DB::getSchemaBuilder()->hasTable('admins')) {
            $this->error("Table 'admins' was not found. Migrate/import the mother system schema first.");
            return self::FAILURE;
        }

        $username = trim((string) ($this->option('username') ?: env('FINOT_ADMIN_USERNAME', 'admin')));
        $email = strtolower(trim((string) ($this->option('email') ?: env('FINOT_ADMIN_EMAIL', ''))));
        $fullName = trim((string) ($this->option('full-name') ?: env('FINOT_ADMIN_FULL_NAME', 'System Administrator')));
        $password = (string) ($this->option('password') ?: env('FINOT_ADMIN_PASSWORD', ''));
        $role = trim((string) ($this->option('role') ?: env('FINOT_ADMIN_ROLE', 'super_admin')));
        $status = trim((string) ($this->option('status') ?: env('FINOT_ADMIN_STATUS', 'active')));
        $updateExisting = (bool) $this->option('update-existing');

        if ($username === '') {
            $this->error('Username is required.');
            return self::FAILURE;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email is invalid.');
            return self::FAILURE;
        }

        if ($role === '') {
            $this->error('Role is required.');
            return self::FAILURE;
        }

        if ($status === '') {
            $this->error('Status is required.');
            return self::FAILURE;
        }

        $existing = DB::table('admins')->where('username', $username)->first();
        if ($existing) {
            if (!$updateExisting) {
                $this->info("Admin '{$username}' already exists. No changes made.");
                return self::SUCCESS;
            }

            $updates = [
                'full_name' => $fullName,
                'role' => $role,
                'status' => $status,
            ];

            if ($email !== '') {
                $emailTaken = DB::table('admins')
                    ->where('email', $email)
                    ->where('username', '!=', $username)
                    ->exists();
                if ($emailTaken) {
                    $this->error("Email '{$email}' is already used by another admin.");
                    return self::FAILURE;
                }
                $updates['email'] = $email;
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    $this->error('Password must be at least 6 characters.');
                    return self::FAILURE;
                }
                $updates['password_hash'] = Hash::make($password);
            }

            DB::table('admins')->where('username', $username)->update($updates);
            $this->info("Admin '{$username}' updated.");
            return self::SUCCESS;
        }

        if ($password === '') {
            $this->error('Password is required to create a new admin. Set FINOT_ADMIN_PASSWORD or use --password.');
            return self::FAILURE;
        }

        if (strlen($password) < 6) {
            $this->error('Password must be at least 6 characters.');
            return self::FAILURE;
        }

        if ($email !== '') {
            $emailTaken = DB::table('admins')->where('email', $email)->exists();
            if ($emailTaken) {
                $this->error("Email '{$email}' is already used by another admin.");
                return self::FAILURE;
            }
        }

        DB::table('admins')->insert([
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'full_name' => $fullName,
            'password_hash' => Hash::make($password),
            'role' => $role,
            'status' => $status,
        ]);

        $this->info("Admin '{$username}' created.");
        return self::SUCCESS;
    }
}
