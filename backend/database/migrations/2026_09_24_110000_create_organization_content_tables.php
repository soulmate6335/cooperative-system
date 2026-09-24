<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organization-facing content: notices (draft -> published -> archived),
 * executive profiles, and the single active homepage content record.
 *
 * Content is the smallest sensible schema for the cooperative's public and
 * member-facing surfaces. No CMS-like flexibility is introduced: notices carry
 * a status lifecycle, executives are ordered/visible profiles, and homepage
 * content is a single active record guarded by a partial unique index so two
 * conflicting homepage configurations cannot exist at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('body');
            $table->string('excerpt')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('visibility')->default('public')->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE notices ADD CONSTRAINT notices_status_valid CHECK (status IN ('draft', 'published', 'archived'))");
        DB::statement("ALTER TABLE notices ADD CONSTRAINT notices_visibility_valid CHECK (visibility IN ('public', 'members'))");
        DB::statement('ALTER TABLE notices ADD CONSTRAINT notices_date_range_valid CHECK (expires_at IS NULL OR publish_at IS NULL OR expires_at >= publish_at)');

        Schema::create('executives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('position');
            $table->text('biography')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_visible')->default(true)->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_content', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('hero_title')->nullable();
            $table->text('hero_description')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->text('introduction')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Only one active homepage configuration may exist at any time.
        DB::statement('CREATE UNIQUE INDEX organization_content_single_active ON organization_content ((true)) WHERE is_active');
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_content');
        Schema::dropIfExists('executives');
        Schema::dropIfExists('notices');
    }
};
