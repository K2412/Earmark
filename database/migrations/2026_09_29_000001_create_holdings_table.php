<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1260: manually maintained investment holdings. No brokerage sync or
     * trading — the user enters cost basis and market value, plus an optional
     * target allocation. Performance is derived transparently from entered values.
     */
    public function up(): void
    {
        Schema::create('holdings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->string('asset_class'); // equity | fixed_income | cash | real_estate | other
            $table->bigInteger('cost_basis_cents')->default(0);
            $table->bigInteger('market_value_cents')->default(0);
            $table->unsignedInteger('target_allocation_bps')->nullable();
            $table->string('currency', 3)->default('CAD');
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['household_id', 'asset_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holdings');
    }
};
