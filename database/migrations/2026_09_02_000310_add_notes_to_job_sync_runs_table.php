<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Non-error information from a source run (throttled, listings skipped…). */
    public function up(): void
    {
        Schema::table('job_sync_runs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('job_sync_runs', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
