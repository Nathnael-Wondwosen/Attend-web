<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('att_term_definitions', function (Blueprint $table) {
            if (!Schema::hasColumn('att_term_definitions', 'status')) {
                $table->string('status', 16)->default('draft')->after('is_active');
            }
            if (!Schema::hasColumn('att_term_definitions', 'approved_by_admin_id')) {
                $table->unsignedBigInteger('approved_by_admin_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('att_term_definitions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_admin_id');
            }
            if (!Schema::hasColumn('att_term_definitions', 'locked_by_admin_id')) {
                $table->unsignedBigInteger('locked_by_admin_id')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('att_term_definitions', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('locked_by_admin_id');
            }
        });

        Schema::table('att_term_definitions', function (Blueprint $table) {
            $table->index(['academic_year', 'status'], 'att_term_definitions_year_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('att_term_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('att_term_definitions', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
            if (Schema::hasColumn('att_term_definitions', 'locked_by_admin_id')) {
                $table->dropColumn('locked_by_admin_id');
            }
            if (Schema::hasColumn('att_term_definitions', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('att_term_definitions', 'approved_by_admin_id')) {
                $table->dropColumn('approved_by_admin_id');
            }
            if (Schema::hasColumn('att_term_definitions', 'status')) {
                $table->dropColumn('status');
            }
            try {
                $table->dropIndex('att_term_definitions_year_status_idx');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};
