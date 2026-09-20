<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('meeting_date');
            $table->string('meeting_type')->default('loan_committee');
            $table->unsignedInteger('cutoff_days')->default(14);
            $table->string('status')->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE committee_meetings ADD CONSTRAINT committee_meetings_cutoff_days_positive CHECK (cutoff_days > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_meetings');
    }
};
