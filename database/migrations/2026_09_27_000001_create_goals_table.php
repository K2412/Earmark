<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1254: save-up and pay-down goals. Progress is grounded in real
     * balances via optional links to an account, bucket, or financial position;
     * debt goals carry principal, APR, and required payment for payoff
     * projection. Goals never touch the ledger.
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // save_up | pay_down
            $table->bigInteger('target_amount')->default(0);
            $table->date('target_date')->nullable();
            $table->unsignedInteger('apr_bps')->nullable();
            $table->bigInteger('required_payment')->nullable();
            $table->bigInteger('principal')->nullable();
            $table->foreignUlid('linked_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('linked_bucket_id')->nullable()->constrained('buckets')->nullOnDelete();
            $table->foreignUlid('linked_position_id')->nullable()->constrained('financial_positions')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['household_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
