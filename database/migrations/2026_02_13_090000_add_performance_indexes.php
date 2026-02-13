<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attendance queries are dominated by marked_at filters and session/status aggregations.
        Schema::table('att_attendance', function (Blueprint $table) {
            $table->index('marked_at', 'att_attendance_marked_at_idx');
            $table->index(['session_id', 'status'], 'att_attendance_session_status_idx');
        });

        // Token rotation / session lifecycle queries filter by status + token_expires_at.
        Schema::table('att_sessions', function (Blueprint $table) {
            $table->index(['status', 'token_expires_at'], 'att_sessions_status_token_exp_idx');
        });

        // Roster membership checks need a fast composite index (table is part of the roster schema).
        if (Schema::hasTable('class_enrollments')) {
            Schema::table('class_enrollments', function (Blueprint $table) {
                $table->index(['class_id', 'student_id', 'status'], 'class_enrollments_class_student_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('att_attendance', function (Blueprint $table) {
            $table->dropIndex('att_attendance_marked_at_idx');
            $table->dropIndex('att_attendance_session_status_idx');
        });

        Schema::table('att_sessions', function (Blueprint $table) {
            $table->dropIndex('att_sessions_status_token_exp_idx');
        });

        if (Schema::hasTable('class_enrollments')) {
            Schema::table('class_enrollments', function (Blueprint $table) {
                $table->dropIndex('class_enrollments_class_student_status_idx');
            });
        }
    }
};

