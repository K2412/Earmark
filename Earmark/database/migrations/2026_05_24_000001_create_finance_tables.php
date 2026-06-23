<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->enum('type', ['chequing', 'savings', 'credit_card', 'cash', 'investment', 'other']);
            $table->bigInteger('starting_balance')->default(0);
            $table->date('starting_balance_date');
            $table->boolean('archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->enum('type', ['income', 'housing', 'transportation', 'food', 'household', 'personal', 'health', 'debt', 'savings', 'fees', 'other']);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('archived')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('buckets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->enum('kind', ['system', 'goal', 'ongoing'])->default('ongoing');
            $table->bigInteger('monthly_obligation')->default(0);
            $table->bigInteger('target_amount')->nullable();
            $table->date('target_date');
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bucket_obligation_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bucket_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('monthly_obligation');
            $table->bigInteger('target_amount')->nullable();
            $table->date('target_date');
            $table->unsignedSmallInteger('effective_year');
            $table->unsignedTinyInteger('effective_month');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->index(['bucket_id', 'effective_year', 'effective_month']);
        });

        Schema::create('bucket_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('from_bucket_id')->nullable()->constrained('buckets')->nullOnDelete();
            $table->foreignUlid('to_bucket_id')->constrained('buckets')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->bigInteger('amount');
            $table->string('memo')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->index(['from_bucket_id', 'year', 'month']);
            $table->index(['to_bucket_id', 'year', 'month']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('date');
            $table->foreignUlid('account_id')->constrained();
            $table->string('payee');
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('bucket_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount');
            $table->string('memo')->nullable();
            $table->boolean('is_split')->default(false);
            $table->boolean('cleared')->default(false);
            $table->boolean('reconciled')->default(false);
            $table->ulid('transfer_pair_id')->nullable()->index();
            $table->enum('source', ['manual', 'imported_pdf', 'imported_csv'])->default('manual');
            $table->ulid('import_batch_id')->nullable()->index();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->index(['account_id', 'date']);
            $table->index(['category_id', 'date']);
            $table->index(['bucket_id', 'date']);
        });

        Schema::create('transaction_splits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('bucket_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('memo')->nullable();
            $table->timestamps();
            $table->index('transaction_id');
            $table->index('category_id');
            $table->index('bucket_id');
        });

        Schema::create('payee_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('pattern');
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('bucket_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('auto_apply')->default(true);
            $table->timestamps();
        });

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->date('statement_date');
            $table->bigInteger('statement_balance');
            $table->bigInteger('calculated_balance');
            $table->enum('status', ['matched', 'discrepancy_accepted']);
            $table->bigInteger('discrepancy_amount')->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('reconciled_by_user_id')->constrained('users');
            $table->timestamp('reconciled_at');
            $table->timestamps();
        });

        Schema::create('statement_uploads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_sha256', 64)->unique();
            $table->unsignedBigInteger('file_size_bytes');
            $table->enum('status', ['parsing', 'parsed', 'failed', 'imported']);
            $table->unsignedInteger('parsed_transaction_count')->default(0);
            $table->unsignedInteger('imported_transaction_count')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        Schema::create('staged_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('statement_upload_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('payee');
            $table->string('raw_payee');
            $table->bigInteger('amount');
            $table->foreignUlid('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUlid('suggested_bucket_id')->nullable()->constrained('buckets')->nullOnDelete();
            $table->foreignUlid('final_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUlid('final_bucket_id')->nullable()->constrained('buckets')->nullOnDelete();
            $table->boolean('accept')->default(true);
            $table->boolean('is_split')->default(false);
            $table->foreignUlid('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staged_transaction_splits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('staged_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('bucket_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staged_transaction_splits');
        Schema::dropIfExists('staged_transactions');
        Schema::dropIfExists('statement_uploads');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('payee_rules');
        Schema::dropIfExists('transaction_splits');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('bucket_assignments');
        Schema::dropIfExists('bucket_obligation_versions');
        Schema::dropIfExists('buckets');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('accounts');
    }
};
