<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attendance-app owned table. Does not modify the school's master roster tables.
        Schema::create('att_teacher_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('teacher_id');
            $table->string('username', 64)->unique();
            $table->string('password_hash');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();

            $table->unique('teacher_id');
            $table->index(['status', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_teacher_accounts');
    }
};

