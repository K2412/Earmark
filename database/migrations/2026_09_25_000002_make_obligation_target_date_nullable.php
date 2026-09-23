<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1252: an ongoing bucket's obligation version has no target date, so
     * `target_date` must be nullable. The original column was NOT NULL.
     */
    public function up(): void
    {
        Schema::table('bucket_obligation_versions', function (Blueprint $table) {
            $table->date('target_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bucket_obligation_versions', function (Blueprint $table) {
            $table->date('target_date')->nullable(false)->change();
        });
    }
};
