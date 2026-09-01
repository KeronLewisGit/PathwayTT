<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('provider');
            $table->string('slug')->unique();
            // App\Enums: local_tt | international_online | hybrid.
            $table->string('provider_type')->index();
            $table->string('delivery_mode')->nullable(); // in_person|online|blended
            $table->text('url')->nullable();
            // Costs are nullable by design — never invented; admin fills them.
            $table->unsignedBigInteger('cost_min_cents')->nullable();
            $table->unsignedBigInteger('cost_max_cents')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('cost_note')->nullable();
            $table->unsignedSmallInteger('duration_weeks')->nullable();
            $table->string('credential_type')->nullable(); // certificate|diploma|degree|badge|professional
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_resources');
    }
};
