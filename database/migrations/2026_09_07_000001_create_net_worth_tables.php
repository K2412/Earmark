<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_positions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->enum('classification', ['asset', 'liability']);
            $table->enum('purpose', ['investable', 'primary_residence', 'home_purchase', 'other'])->default('other');
            $table->boolean('archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('valuations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('financial_position_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->date('valued_at');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->index(['financial_position_id', 'valued_at']);
        });

        Schema::create('household_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('target_cents');
            $table->unsignedSmallInteger('target_year');
            $table->unsignedInteger('inflation_bps')->default(200);
            $table->unsignedInteger('return_bps')->default(700);
            $table->bigInteger('windfall_cents')->default(0);
            $table->unsignedSmallInteger('windfall_year')->nullable();
            $table->timestamps();
        });

        Schema::create('contribution_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('household_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->bigInteger('annual_contribution_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_phases');
        Schema::dropIfExists('household_plans');
        Schema::dropIfExists('valuations');
        Schema::dropIfExists('financial_positions');
    }
};
