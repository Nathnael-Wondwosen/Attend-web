<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('att_take_tokens')) {
            return;
        }

        Schema::create('att_take_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            // sha256 hex (64 chars)
            $table->string('token_hash', 64)->unique();
            $table->string('label', 80)->nullable();
            $table->string('status', 16)->default('active'); // active|disabled
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status'], 'att_take_tokens_teacher_status_idx');
            $table->index(['expires_at', 'status'], 'att_take_tokens_expires_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_take_tokens');
    }
};

