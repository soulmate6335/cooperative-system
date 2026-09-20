<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('guarantor_member_id')->constrained('members')->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            $table->foreignUuid('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_note')->nullable();
            $table->timestamps();
            $table->unique(['loan_application_id', 'guarantor_member_id'], 'loan_guarantors_unique_applicant');
            $table->index(['guarantor_member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};
