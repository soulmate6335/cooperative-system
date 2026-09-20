<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->string('account_type');
            $table->string('account_number')->unique();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['member_id', 'account_type']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->timestamp('payment_date');
            $table->string('reference_number')->unique();
            $table->string('purpose');
            $table->string('status')->default('pending')->index();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_minor_positive CHECK (amount_minor > 0)');

        Schema::create('receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('file_path')->nullable();
            $table->timestamp('issued_at');
            $table->foreignUuid('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('reverses_transaction_id')->nullable()->unique();
            $table->string('transaction_type');
            $table->string('direction');
            $table->bigInteger('amount_minor');
            $table->timestamp('transaction_date');
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status', 'transaction_date']);
        });
        DB::statement('ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_amount_positive CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_direction_valid CHECK (direction IN ('credit', 'debit'))");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_status_valid CHECK (status IN ('pending', 'posted'))");
        DB::statement('ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_reverses_transaction_id_foreign FOREIGN KEY (reverses_transaction_id) REFERENCES financial_transactions (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('payment_methods');
    }
};
