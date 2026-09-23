<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the dormant import staging tables for task #1247: per-household file
     * fingerprints, import source/parser provenance, and row-level duplicate
     * evidence produced during staging.
     */
    public function up(): void
    {
        Schema::table('statement_uploads', function (Blueprint $table) {
            $table->dropUnique(['file_sha256']);
            $table->string('source')->default('csv')->after('account_id');
            $table->string('parser_version')->nullable()->after('status');
            $table->unique(['household_id', 'file_sha256']);
        });

        Schema::table('staged_transactions', function (Blueprint $table) {
            $table->string('row_fingerprint', 64)->nullable()->after('raw_payee');
            $table->boolean('is_possible_duplicate')->default(false)->after('accept');
            $table->string('duplicate_reason')->nullable()->after('is_possible_duplicate');
            $table->foreignUlid('duplicate_of_transaction_id')->nullable()->after('duplicate_reason')
                ->constrained('transactions')->nullOnDelete();
            $table->index('row_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('staged_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_transaction_id');
            $table->dropIndex(['row_fingerprint']);
            $table->dropColumn(['row_fingerprint', 'is_possible_duplicate', 'duplicate_reason']);
        });

        Schema::table('statement_uploads', function (Blueprint $table) {
            $table->dropUnique(['household_id', 'file_sha256']);
            $table->dropColumn(['source', 'parser_version']);
            $table->unique('file_sha256');
        });
    }
};
