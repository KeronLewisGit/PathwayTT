<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('proficiency')->nullable(); // 1–5
            $table->decimal('years_used', 4, 1)->nullable();
            // resume | self_reported | certificate
            $table->string('evidence_source')->default('self_reported');
            // Re-parses must never overwrite user-confirmed rows.
            $table->boolean('is_user_edited')->default(false);
            $table->timestamps();
            $table->unique(['profile_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_skill');
    }
};
