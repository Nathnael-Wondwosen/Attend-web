<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_mobile_sync', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_account_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->integer('class_id');
            $table->date('attendance_date');
            $table->string('device_id', 80)->nullable();
            $table->char('client_session_id', 36); // UUID from device
            $table->char('payload_hash', 64); // sha256 of normalized payload
            $table->json('result_json')->nullable();
            $table->timestamps();

            $table->unique('client_session_id', 'att_mobile_sync_client_session_unique');
            $table->index(['teacher_account_id', 'created_at'], 'att_mobile_sync_teacher_created_idx');
            $table->index(['class_id', 'attendance_date'], 'att_mobile_sync_class_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_mobile_sync');
    }
};

