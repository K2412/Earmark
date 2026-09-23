<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1263: a household can choose which overview cards to show. Null means
     * "all cards" (the default).
     */
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->json('overview_cards')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('overview_cards');
        });
    }
};
