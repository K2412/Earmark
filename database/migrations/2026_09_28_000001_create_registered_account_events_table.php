<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1257: a distinct-event log for Canadian registered programmes.
     * Contributions, HBP withdrawals, designated HBP repayments, and authoritative
     * CRA room overrides are all separate event rows, layered on accounts rather
     * than on balances. Room is per owner (never combined across spouses).
     */
    public function up(): void
    {
        Schema::create('registered_account_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // contribution | hbp_withdrawal | hbp_repayment | room_override
            $table->bigInteger('amount');
            $table->date('occurred_on');
            $table->unsignedSmallInteger('plan_year');
            $table->date('as_of')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['account_id', 'type', 'plan_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registered_account_events');
    }
};
