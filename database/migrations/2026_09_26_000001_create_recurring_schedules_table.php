<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1253: confirmed recurring schedules. Candidates are inferred on the
     * fly from reviewed history and are never persisted until confirmed; a
     * persisted row (confirmed | paused | dismissed) also suppresses re-detection
     * of that payee.
     */
    public function up(): void
    {
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('amount');
            $table->string('frequency');
            $table->date('next_due_date')->nullable();
            $table->string('status')->default('confirmed');
            $table->boolean('detected')->default(false);
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['household_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_schedules');
    }
};
