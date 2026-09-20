<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('decided_by')->constrained('users')->restrictOnDelete();
            $table->string('decision');
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->unsignedInteger('interest_rate_basis_points')->nullable();
            $table->string('interest_method')->nullable();
            $table->unsignedInteger('repayment_months')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE loan_decisions ADD CONSTRAINT loan_decisions_interest_method_valid CHECK (interest_method IS NULL OR interest_method IN ('flat', 'reducing_balance'))");
        DB::statement("ALTER TABLE loan_decisions ADD CONSTRAINT loan_decisions_approved_amount_valid CHECK (decision <> 'approved' OR approved_amount_minor > 0)");
        DB::statement("ALTER TABLE loan_decisions ADD CONSTRAINT loan_decisions_terms_valid CHECK ((decision <> 'approved') OR (interest_rate_basis_points IS NOT NULL AND interest_method IS NOT NULL AND repayment_months IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_decisions');
    }
};
