<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_resource_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            // How strongly this resource develops the skill (gap-plan ranking input).
            $table->unsignedTinyInteger('impact_weight')->default(1);
            $table->timestamps();
            $table->unique(['learning_resource_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_resource_skill');
    }
};
