<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_investigations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('investigation_date')->nullable();
            $table->text('member_findings')->nullable();
            $table->text('savings_findings')->nullable();
            $table->text('shares_findings')->nullable();
            $table->text('existing_loan_findings')->nullable();
            $table->text('guarantor_findings')->nullable();
            $table->text('committee_comments')->nullable();
            $table->text('recommendation')->nullable();
            $table->string('status')->default('assigned')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_investigations');
    }
};
