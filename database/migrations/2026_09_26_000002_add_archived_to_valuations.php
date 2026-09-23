<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1256: valuations can be archived (a correction that shouldn't count)
     * without deleting the record, so prior snapshots remain reproducible.
     */
    public function up(): void
    {
        Schema::table('valuations', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('valued_at');
        });
    }

    public function down(): void
    {
        Schema::table('valuations', function (Blueprint $table) {
            $table->dropColumn('archived');
        });
    }
};
