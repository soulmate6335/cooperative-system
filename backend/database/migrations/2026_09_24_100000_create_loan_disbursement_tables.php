<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loan disbursement + repayment bookkeeping tables. These describe loan
     * obligations and allocations only; the existing Financial Core remains
     * authoritative for posted money movement (financial_transactions,
     * account balances, payments, receipts).
     */
    public function up(): void
    {
        Schema::create('loan_disbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // A single full disbursement per loan is enforced at the schema level.
            $table->foreignUuid('loan_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->foreignUuid('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->string('status')->default('posted')->index();
            $table->timestamp('disbursed_at');
            $table->foreignUuid('authorized_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE loan_disbursements ADD CONSTRAINT loan_disbursements_amount_positive CHECK (amount_minor > 0)');

        Schema::create('loan_installments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('principal_due_minor');
            $table->unsignedBigInteger('interest_due_minor');
            $table->unsignedBigInteger('total_due_minor');
            $table->string('status')->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['loan_id', 'installment_number']);
        });
        DB::statement('ALTER TABLE loan_installments ADD CONSTRAINT loan_installments_due_positive CHECK (total_due_minor > 0)');
        DB::statement('ALTER TABLE loan_installments ADD CONSTRAINT loan_installments_amounts_consistent CHECK (principal_due_minor >= 0 AND interest_due_minor >= 0 AND total_due_minor = principal_due_minor + interest_due_minor)');

        // Repayment allocation history is append-only: amounts are immutable,
        // and a reversal transitions a row to "voided" rather than editing it.
        Schema::create('loan_repayment_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('financial_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('loan_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('installment_id')->constrained('loan_installments')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('principal_allocated_minor');
            $table->unsignedBigInteger('interest_allocated_minor');
            $table->string('status')->default('posted')->index();
            $table->foreignUuid('voided_by_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE loan_repayment_allocations ADD CONSTRAINT loan_repayment_allocations_amount_positive CHECK (amount_minor > 0)');
        DB::statement('ALTER TABLE loan_repayment_allocations ADD CONSTRAINT loan_repayment_allocations_split_consistent CHECK (principal_allocated_minor >= 0 AND interest_allocated_minor >= 0 AND amount_minor = principal_allocated_minor + interest_allocated_minor)');

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignUuid('loan_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loan_id');
        });
        Schema::dropIfExists('loan_repayment_allocations');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loan_disbursements');
    }
};
