<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administrative eligibility decisions for a member + loan product pair.
 *
 * The system calculates eligibility factors, but only an authorized
 * administrator may grant or deny the right to apply. Each decision is a
 * new immutable row so the decision history is preserved (pending is
 * derived from the absence of a decision). An explicit override is
 * recorded the same way every other decision is: status + reason + who
 * decided + when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_eligibility_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('loan_product_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->foreignUuid('decided_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['member_id', 'loan_product_id', 'decided_at']);
        });

        DB::statement("ALTER TABLE loan_eligibility_decisions ADD CONSTRAINT loan_eligibility_decisions_status_valid CHECK (status IN ('eligible', 'ineligible'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_eligibility_decisions');
    }
};