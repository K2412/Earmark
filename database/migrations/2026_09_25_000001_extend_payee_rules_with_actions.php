<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1250: give payee rules a transparent, auditable action set — a label,
     * enable/disable, rename, hide-from-reports, and mark-for-review — on top of
     * the existing categorize/bucket actions. Rules never split or delete.
     * `excluded_from_reports` on transactions carries the hide action into the
     * ledger for reporting (#1255) to honour.
     */
    public function up(): void
    {
        Schema::table('payee_rules', function (Blueprint $table) {
            $table->string('name')->nullable()->after('household_id');
            $table->boolean('enabled')->default(true)->after('pattern');
            $table->string('rename_to')->nullable()->after('bucket_id');
            $table->boolean('hide_from_reports')->default(false)->after('rename_to');
            $table->boolean('mark_for_review')->default(false)->after('hide_from_reports');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('excluded_from_reports')->default(false)->after('reviewed');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('excluded_from_reports');
        });

        Schema::table('payee_rules', function (Blueprint $table) {
            $table->dropColumn(['name', 'enabled', 'rename_to', 'hide_from_reports', 'mark_for_review']);
        });
    }
};
