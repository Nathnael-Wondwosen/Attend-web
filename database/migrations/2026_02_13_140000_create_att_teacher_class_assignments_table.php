<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('att_teacher_class_assignments')) {
            return;
        }

        Schema::create('att_teacher_class_assignments', function (Blueprint $table) {
            $table->id();
            // teacher_id refers to the roster teacher in `teachers` (master DB table).
            $table->unsignedBigInteger('teacher_id');
            // class_id refers to the roster class in `classes` (master DB table).
            $table->unsignedBigInteger('class_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['teacher_id', 'class_id'], 'att_teacher_class_assignments_unique');
            $table->index(['teacher_id', 'is_active'], 'att_tca_teacher_active_idx');
            $table->index(['class_id', 'is_active'], 'att_tca_class_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_teacher_class_assignments');
    }
};

