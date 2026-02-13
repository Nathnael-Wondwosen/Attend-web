<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Guardrail: do not seed master roster tables unless explicitly enabled.
        if (app()->environment('production') || !config('finot.roster.allow_seeding')) {
            $this->command?->warn('DatabaseSeeder: roster seeding disabled (nothing to seed).');
            return;
        }

        $this->call([AttendanceSeeder::class]);
    }
}
