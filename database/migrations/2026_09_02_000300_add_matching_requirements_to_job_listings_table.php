<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured requirements the scorer needs (Phase 4). All nullable:
     * when a listing doesn't state them, the matching component is simply
     * not applicable and its weight is redistributed — never guessed.
     */
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            // Years required; falls back to a seniority-based estimate (config/matching.php).
            $table->unsignedTinyInteger('min_years_experience')->nullable()->after('seniority');
            // App\Enums\QualificationType value.
            $table->string('min_education_level')->nullable()->after('min_years_experience');
            // Employer requires the right to work in its own country → hard filter for T&T residents.
            $table->boolean('requires_work_permit')->nullable()->after('required_overlap_hours');
            // Local credentials the role insists on: nis|bir|drivers_permit|police_certificate.
            $table->json('required_credentials')->nullable()->after('requires_work_permit');
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['min_years_experience', 'min_education_level', 'requires_work_permit', 'required_credentials']);
        });
    }
};
