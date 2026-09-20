<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('minimum_membership_months')->default(6);
            $table->unsignedBigInteger('minimum_amount_minor')->default(0);
            $table->unsignedBigInteger('maximum_amount_minor')->nullable();
            $table->unsignedInteger('interest_rate_basis_points')->nullable();
            $table->string('interest_method')->nullable();
            $table->unsignedInteger('repayment_months')->default(11);
            $table->unsignedInteger('required_guarantors')->default(2);
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE loan_products ADD CONSTRAINT loan_products_interest_method_valid CHECK (interest_method IS NULL OR interest_method IN (\'flat\', \'reducing_balance\'))');
        DB::statement('ALTER TABLE loan_products ADD CONSTRAINT loan_products_maximum_amount_valid CHECK (maximum_amount_minor IS NULL OR maximum_amount_minor >= minimum_amount_minor)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
