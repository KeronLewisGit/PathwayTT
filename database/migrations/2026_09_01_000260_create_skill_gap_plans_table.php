<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_gap_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_industry_id')->nullable()->constrained('industries')->nullOnDelete();
            $table->timestamp('generated_at');
            // Full snapshot of the generated plan so it is reproducible even
            // after jobs/resources change.
            $table->json('payload');
            $table->timestamps();

            $table->index(['user_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_gap_plans');
    }
};
