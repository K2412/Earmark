<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support OFX/QFX/QBO/QIF bank exports (#1269) alongside CSV. Widen the ledger
     * source from a fixed enum to a string so per-format provenance can be recorded,
     * and carry the bank-assigned transaction id (OFX FITID) from staging through to
     * the ledger so future imports can dedupe on it exactly.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source')->default('manual')->change();
            $table->string('external_id')->nullable()->after('import_batch_id');
            $table->index(['account_id', 'external_id']);
        });

        Schema::table('staged_transactions', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('row_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('staged_transactions', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'external_id']);
            $table->dropColumn('external_id');
            $table->enum('source', ['manual', 'imported_pdf', 'imported_csv'])->default('manual')->change();
        });
    }
};
