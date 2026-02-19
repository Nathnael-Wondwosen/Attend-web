<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('att_term_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('academic_year');
            $table->string('term_key', 16);
            $table->string('term_label', 120)->nullable();
            $table->unsignedTinyInteger('term_order')->default(0);
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academic_year', 'term_key'], 'att_term_definitions_year_key_uniq');
            $table->index(['academic_year', 'term_order'], 'att_term_definitions_year_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_term_definitions');
    }
};
