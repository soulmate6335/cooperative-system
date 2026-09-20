<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('application_number')->unique();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('committee_meeting_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_requested_minor');
            $table->string('purpose');
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('savings_balance_minor')->nullable();
            $table->unsignedBigInteger('shares_balance_minor')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'status']);
        });

        DB::statement('ALTER TABLE loan_applications ADD CONSTRAINT loan_applications_amount_requested_positive CHECK (amount_requested_minor > 0)');
        DB::statement("CREATE UNIQUE INDEX loan_applications_one_open_per_member ON loan_applications (member_id) WHERE status NOT IN ('approved', 'rejected', 'cancelled')");
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
