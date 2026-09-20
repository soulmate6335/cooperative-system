<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->string('loan_number')->unique();
            $table->unsignedBigInteger('principal_amount_minor');
            $table->unsignedInteger('interest_rate_basis_points')->nullable();
            $table->string('interest_method')->nullable();
            $table->unsignedInteger('repayment_months')->nullable();
            $table->unsignedBigInteger('interest_amount_minor')->nullable();
            $table->unsignedBigInteger('total_payable_minor')->nullable();
            $table->unsignedBigInteger('disbursed_amount_minor')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('maturity_date')->nullable();
            $table->string('status')->default('pending_disbursement')->index();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE loans ADD CONSTRAINT loans_principal_positive CHECK (principal_amount_minor > 0)');
        DB::statement("ALTER TABLE loans ADD CONSTRAINT loans_interest_method_valid CHECK (interest_method IS NULL OR interest_method IN ('flat', 'reducing_balance'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
