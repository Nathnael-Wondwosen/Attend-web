<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\ClassModel;
use App\Models\ClassTeacher;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AttendanceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Guardrail: never touch master roster data on production/shared deployments.
        // This seeder is for local/dev/demo environments only.
        if (app()->environment('production') || !config('finot.roster.allow_seeding')) {
            $this->command?->warn('AttendanceSeeder skipped (roster seeding disabled).');
            return;
        }

        // Create admin user
        Admin::create([
            'username' => 'admin',
            'email' => 'admin@finot.edu',
            'full_name' => 'System Administrator',
            'password_hash' => Hash::make('admin123'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Create sample teachers
        $teachers = Teacher::factory()->count(3)->create();

        // Create sample classes
        $classes = ClassModel::factory()->count(5)->create();

        // Assign teachers to classes
        foreach ($classes as $index => $class) {
            ClassTeacher::create([
                'class_id' => $class->id,
                'teacher_id' => $teachers[$index % count($teachers)]->id,
                'role' => 'primary',
                'assigned_date' => now(),
                'is_active' => true,
            ]);
        }

        // Create sample students
        $students = Student::factory()->count(50)->create();

        // Enroll students in classes (simplified - 10 students per class)
        foreach ($classes as $classIndex => $class) {
            $studentsForClass = $students->skip($classIndex * 10)->take(10);
            foreach ($studentsForClass as $student) {
                DB::table('class_enrollments')->insert([
                    'class_id' => $class->id,
                    'student_id' => $student->id,
                    'status' => 'active',
                    // Live schema uses enrollment_date (date) not enrolled_at.
                    'enrollment_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Sample data seeded successfully!');
        $this->command->info('Admin login: admin / admin123');
    }
}
