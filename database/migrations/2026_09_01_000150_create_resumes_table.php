<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            // Stored on the "private" disk, outside the public webroot.
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->longText('extracted_text')->nullable();
            // App\Enums\ParseStatus: pending|processing|parsed|failed.
            $table->string('parse_status')->default('pending')->index();
            $table->text('parse_error')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
