<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1245: give accounts Canadian metadata. The `type` enum is relaxed to a
     * string so registered account types (TFSA/RRSP/FHSA/RESP, etc.) can be added
     * — the allowed set is enforced in validation (App\Models\Account::TYPES).
     * Currency is explicit (CAD for now) so multi-currency can arrive later without
     * pretending exchange-rate support exists.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('type')->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('household_id')->constrained('users')->nullOnDelete();
            $table->string('institution')->nullable()->after('name');
            $table->string('currency', 3)->default('CAD')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['institution', 'currency']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('type', ['chequing', 'savings', 'credit_card', 'cash', 'investment', 'other'])->change();
        });
    }
};
