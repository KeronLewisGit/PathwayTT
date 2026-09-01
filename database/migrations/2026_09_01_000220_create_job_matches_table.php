<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            // Line-by-line "why this score" data for the UI.
            $table->json('score_breakdown')->nullable();
            $table->json('missing_skills')->nullable();
            // Hard filters produce ineligibility with a reason, not a low score.
            $table->boolean('is_eligible')->default(true);
            $table->string('ineligibility_reason')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['user_id', 'job_listing_id']);
            $table->index(['user_id', 'is_eligible', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_matches');
    }
};
