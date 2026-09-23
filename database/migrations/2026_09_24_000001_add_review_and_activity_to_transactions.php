<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1246: a review state on ledger transactions plus a household-scoped,
     * human-readable activity trail that records who changed what.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('reviewed')->default(false)->after('cleared');
            $table->index(['household_id', 'reviewed']);
        });

        Schema::create('transaction_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('action');
            $table->string('description');
            $table->timestamps();

            $table->index(['household_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_activities');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['household_id', 'reviewed']);
            $table->dropColumn('reviewed');
        });
    }
};
