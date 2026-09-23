<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lifecycle state for the staged-review workspace (task #1249): a staged row
     * moves pending → promoted (became a ledger transaction) or pending →
     * rejected (discarded during review). The existing `accept` flag selects
     * which pending rows a promotion run will commit.
     */
    public function up(): void
    {
        Schema::table('staged_transactions', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('accept');
            $table->index(['statement_upload_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('staged_transactions', function (Blueprint $table) {
            $table->dropIndex(['statement_upload_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
