<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1259: saved household scenarios. Each records the inputs and the
     * assumptions snapshot it used, so results are reproducible and a later
     * catalogue change never rewrites a saved scenario. Home equity stays outside
     * investable unless an explicit downsizing event transfers proceeds in.
     */
    public function up(): void
    {
        Schema::create('scenarios', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('base_year');
            $table->unsignedSmallInteger('horizon_years');
            $table->bigInteger('starting_investable_cents')->default(0);
            $table->bigInteger('annual_contribution_cents')->default(0);
            $table->unsignedSmallInteger('contribution_years')->default(0);
            $table->unsignedInteger('return_bps')->default(500);
            $table->unsignedInteger('inflation_bps')->default(200);
            $table->bigInteger('windfall_cents')->default(0);
            $table->unsignedSmallInteger('windfall_year')->nullable();
            $table->unsignedSmallInteger('drawdown_start_year')->nullable();
            $table->bigInteger('drawdown_annual_cents')->default(0);
            $table->unsignedSmallInteger('downsizing_year')->nullable();
            $table->bigInteger('downsizing_proceeds_cents')->default(0);
            $table->json('assumptions_snapshot');
            $table->string('formula_version');
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};
