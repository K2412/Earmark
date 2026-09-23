<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1261: a transaction can be assigned to a household member for review.
     * Resolving it (marking reviewed) clears the assignment; the activity trail
     * records who assigned and who resolved.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('review_assignee_id')->nullable()->after('reviewed')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_assignee_id');
        });
    }
};
