<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('att_sessions', function (Blueprint $table) {
            $table->date('attendance_date')->nullable()->after('class_id');
            $table->enum('workflow_status', ['draft', 'submitted'])->default('draft')->after('status');
            $table->timestamp('submitted_at')->nullable()->after('closed_at');
            $table->integer('submitted_by')->nullable()->after('submitted_at');

            $table->index(['class_id', 'attendance_date'], 'att_sessions_class_date_idx');
            $table->index(['attendance_date'], 'att_sessions_date_idx');
        });

        // Backfill attendance_date for existing rows before enforcing constraints.
        DB::statement("UPDATE att_sessions SET attendance_date = DATE(started_at) WHERE attendance_date IS NULL");
        DB::statement("UPDATE att_sessions SET attendance_date = CURDATE() WHERE attendance_date IS NULL");

        // Enforce not-null and dedupe by class_id+date.
        DB::statement("ALTER TABLE att_sessions MODIFY attendance_date DATE NOT NULL");
        DB::statement("ALTER TABLE att_sessions ADD CONSTRAINT att_unique_class_date UNIQUE (class_id, attendance_date)");

        // Attendance statuses: reduce to present/absent/permission.
        // Map existing values before changing ENUM definition.
        DB::statement("UPDATE att_attendance SET status='present' WHERE status='late'");
        DB::statement("UPDATE att_attendance SET status='permission' WHERE status='excused'");
        DB::statement("ALTER TABLE att_attendance MODIFY status ENUM('present','absent','permission') NOT NULL DEFAULT 'present'");
    }

    public function down(): void
    {
        // Re-expand enum to original set (best-effort) and drop new session columns/constraints.
        DB::statement("ALTER TABLE att_attendance MODIFY status ENUM('present','late','excused','absent') NOT NULL DEFAULT 'present'");

        Schema::table('att_sessions', function (Blueprint $table) {
            $table->dropUnique('att_unique_class_date');
            $table->dropIndex('att_sessions_class_date_idx');
            $table->dropIndex('att_sessions_date_idx');
            $table->dropColumn(['attendance_date', 'workflow_status', 'submitted_at', 'submitted_by']);
        });
    }
};

