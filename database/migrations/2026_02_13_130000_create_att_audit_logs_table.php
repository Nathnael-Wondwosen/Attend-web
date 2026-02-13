<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('session_id')->nullable();
            $table->integer('class_id')->nullable();
            $table->integer('student_id')->nullable();
            $table->unsignedBigInteger('attendance_id')->nullable();

            $table->string('action', 64);
            $table->json('meta')->nullable();

            $table->string('actor_type', 32); // admin|teacher|system
            $table->unsignedBigInteger('actor_id')->nullable(); // admins.id or att_teacher_accounts.id

            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['session_id', 'created_at'], 'att_audit_session_created_idx');
            $table->index(['class_id', 'created_at'], 'att_audit_class_created_idx');
            $table->index(['student_id', 'created_at'], 'att_audit_student_created_idx');
            $table->index(['action', 'created_at'], 'att_audit_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_audit_logs');
    }
};

