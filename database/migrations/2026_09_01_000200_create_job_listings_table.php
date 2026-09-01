<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named job_listings (not jobs) because Laravel's database queue driver
     * owns the `jobs` table.
     */
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            // Adapter that produced this record (manual|csv|remotive|...).
            $table->string('source');
            $table->string('source_job_id')->nullable();
            $table->string('title');
            $table->string('company_name')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\WorkArrangement.
            $table->string('work_arrangement');
            // App\Enums\EmploymentType: permanent|contract|temporary.
            $table->string('employment_type')->nullable();
            $table->string('location_text')->nullable();
            $table->string('country', 2)->nullable();
            $table->boolean('is_open_to_caribbean')->nullable();
            // App\Enums\GeoEligibility: worldwide|region_restricted|country_restricted.
            $table->string('geo_eligibility')->nullable();
            $table->unsignedTinyInteger('required_overlap_hours')->nullable();
            $table->string('seniority')->nullable();
            // Money as integer cents with explicit currency.
            $table->unsignedBigInteger('salary_min_cents')->nullable();
            $table->unsignedBigInteger('salary_max_cents')->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('salary_period')->nullable(); // hourly|monthly|yearly
            $table->longText('description')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            // Users always apply on the original posting.
            $table->text('apply_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source', 'source_job_id']);
            $table->index(['is_active', 'industry_id']);
            $table->index(['is_active', 'work_arrangement']);
            $table->index('posted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
