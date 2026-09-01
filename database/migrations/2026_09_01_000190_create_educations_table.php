<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('institution');
            // App\Enums\QualificationType: CSEC|CAPE|Diploma|Associate|BSc|MSc|PhD|Professional.
            $table->string('qualification_type');
            $table->string('field')->nullable();
            $table->date('completed_at')->nullable();
            $table->boolean('is_user_edited')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educations');
    }
};
