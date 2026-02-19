<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!config('finot.admin.auto_ensure_on_migrate')) {
            return;
        }

        if (!Schema::hasTable('admins')) {
            return;
        }

        $username = trim((string) env('FINOT_ADMIN_USERNAME', 'admin'));
        if ($username === '') {
            return;
        }

        $exists = DB::table('admins')->where('username', $username)->exists();
        $password = (string) env('FINOT_ADMIN_PASSWORD', '');

        // Avoid failing migrations when account is missing but no password is configured.
        if (!$exists && $password === '') {
            return;
        }

        Artisan::call('admin:ensure', ['--update-existing' => true]);
    }

    public function down(): void
    {
        // No rollback action: admin record is master data.
    }
};
