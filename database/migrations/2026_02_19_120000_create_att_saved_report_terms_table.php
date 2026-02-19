<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('att_saved_report_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->string('label', 120);
            $table->string('period_type', 32)->nullable();
            $table->string('term_key', 32)->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'from_date', 'to_date'], 'att_saved_report_terms_class_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_saved_report_terms');
    }
};
