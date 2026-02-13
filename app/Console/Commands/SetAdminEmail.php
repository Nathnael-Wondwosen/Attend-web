<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetAdminEmail extends Command
{
    protected $signature = 'admin:set-email
        {username : Admin username to update}
        {email : Email address to set}
        {--overwrite : Overwrite existing email if already set}';

    protected $description = 'Set the email for an admin account (mother system admins table).';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $email = strtolower(trim((string) $this->argument('email')));
        $overwrite = (bool) $this->option('overwrite');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email.');
            return self::FAILURE;
        }

        $row = DB::table('admins')->where('username', $username)->first();
        if (!$row) {
            $this->error("Admin not found for username: {$username}");
            return self::FAILURE;
        }

        $current = (string) ($row->email ?? '');
        if ($current !== '' && !$overwrite) {
            $this->error("Admin already has email set: {$current}. Use --overwrite to replace it.");
            return self::FAILURE;
        }

        $exists = DB::table('admins')
            ->where('email', $email)
            ->where('username', '!=', $username)
            ->exists();
        if ($exists) {
            $this->error("Email is already used by another admin: {$email}");
            return self::FAILURE;
        }

        DB::table('admins')->where('username', $username)->update([
            'email' => $email,
        ]);

        $this->info("Updated admin '{$username}': email '{$current}' -> '{$email}'");
        return self::SUCCESS;
    }
}

