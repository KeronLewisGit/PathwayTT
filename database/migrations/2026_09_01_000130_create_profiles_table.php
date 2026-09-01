<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            // Region within T&T (e.g. Port of Spain, San Fernando, Tobago).
            $table->string('region')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            // App\Enums\QualificationType value.
            $table->string('highest_education_level')->nullable();
            // Local hiring readiness flags.
            $table->boolean('has_nis')->default(false);
            $table->boolean('has_bir')->default(false);
            $table->boolean('has_drivers_permit')->default(false);
            $table->boolean('has_police_certificate')->default(false);
            $table->boolean('willing_to_relocate')->default(false);
            $table->date('availability_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
