<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_sessions', function (Blueprint $table) {
            $table->id();
            $table->integer('class_id');
            $table->unsignedInteger('academic_year')->nullable();
            $table->enum('term', ['1st', '2nd'])->nullable();
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->integer('started_by')->nullable(); // teachers.id or null if system/admin
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->char('current_token', 16)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'started_at']);
        });

        Schema::create('att_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->integer('class_id');
            $table->integer('student_id');
            $table->enum('status', ['present', 'late', 'excused', 'absent'])->default('present');
            $table->enum('method', ['qr', 'manual', 'import'])->default('qr');
            $table->integer('marked_by')->nullable(); // teachers.id
            $table->timestamp('marked_at')->useCurrent();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'student_id'], 'att_unique_session_student');
            $table->index(['class_id', 'student_id']);
            $table->index('student_id');

            $table->foreign('session_id')
                ->references('id')
                ->on('att_sessions')
                ->onDelete('cascade');
        });

        Schema::create('att_session_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->char('token', 16);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('session_id')
                ->references('id')
                ->on('att_sessions')
                ->onDelete('cascade');

            $table->unique('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_session_tokens');
        Schema::dropIfExists('att_attendance');
        Schema::dropIfExists('att_sessions');
    }
};
