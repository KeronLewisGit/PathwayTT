<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate from profiles: preferences change independently and are one
     * of the triggers for queued match recomputation.
     */
    public function up(): void
    {
        Schema::create('job_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            // Arrays of App\Enums\WorkArrangement / EmploymentType values.
            $table->json('work_arrangements')->nullable();
            $table->json('employment_types')->nullable();
            $table->string('seniority')->nullable();
            // Money as integer cents with explicit currency — never floats.
            $table->unsignedBigInteger('min_salary_cents')->nullable();
            $table->char('min_salary_currency', 3)->nullable();
            $table->string('min_salary_period')->nullable(); // hourly|monthly|yearly
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_preferences');
    }
};
