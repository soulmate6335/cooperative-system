<?php

namespace Tests\Feature;

use App\Models\Executive;
use App\Models\Notice;
use App\Models\OrganizationContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class HomepageContentTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    public function test_public_home_returns_an_empty_safe_payload_without_content(): void
    {
        $this->getJson('/api/v1/public/home')
            ->assertOk()
            ->assertJsonPath('data.content', null)
            ->assertJsonPath('data.notices', [])
            ->assertJsonPath('data.executives', []);
    }

    public function test_admin_can_update_homepage_content_and_it_is_publicly_served(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/home-content', [
                'hero_title' => 'Thrift & Credit Cooperative',
                'hero_description' => 'Saving together, growing together.',
                'introduction' => 'We empower members through collective savings and affordable credit.',
            ])
            ->assertOk()
            ->assertJsonPath('data.hero_title', 'Thrift & Credit Cooperative');

        $this->getJson('/api/v1/public/home')
            ->assertOk()
            ->assertJsonPath('data.content.hero_title', 'Thrift & Credit Cooperative')
            ->assertJsonPath('data.content.introduction', 'We empower members through collective savings and affordable credit.');
    }

    public function test_homepage_hero_image_can_be_uploaded_and_served_as_a_url(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'sanctum')
            ->patch('/api/v1/admin/home-content', [])
            ->assertOk();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->patch('/api/v1/admin/home-content', ['hero_image' => UploadedFile::fake()->createWithContent(
                'hero.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                'image/png',
            )])
            ->assertOk();

        $this->assertNotNull($response->json('data.hero_image_url'));
        $this->assertStringContainsString('/storage/homepage/', $response->json('data.hero_image_url'));
        $response->assertJsonMissingPath('data.hero_image_path');

        $content = OrganizationContent::current();
        Storage::disk('public')->assertExists($content->hero_image_path);
    }

    public function test_homepage_hero_image_is_validated(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->actingAs($this->admin(), 'sanctum')
            ->patch('/api/v1/admin/home-content', ['hero_image' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hero_image');
    }

    public function test_only_one_active_organization_content_row_exists_after_updates(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/home-content', ['hero_title' => 'First title'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/home-content', ['hero_title' => 'Second title'])
            ->assertOk();

        $this->assertSame(1, OrganizationContent::query()->where('is_active', true)->count());
        $this->assertSame('Second title', OrganizationContent::current()->hero_title);
    }

    public function test_public_home_includes_only_public_visible_notices(): void
    {
        Notice::create(['title' => 'Public AGM', 'body' => 'Open to all.', 'status' => 'published', 'publish_at' => now(), 'visibility' => 'public']);
        Notice::create(['title' => 'Members only', 'body' => 'Members.', 'status' => 'published', 'publish_at' => now(), 'visibility' => 'members']);
        Notice::create(['title' => 'Draft', 'body' => 'Hidden.', 'status' => 'draft']);
        Notice::create(['title' => 'Archived', 'body' => 'Hidden.', 'status' => 'archived']);

        $this->getJson('/api/v1/public/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.notices')
            ->assertJsonPath('data.notices.0.title', 'Public AGM');
    }

    public function test_public_home_includes_only_visible_executives_in_order(): void
    {
        Executive::create(['name' => 'Second', 'position' => 'Treasurer', 'display_order' => 2]);
        Executive::create(['name' => 'First', 'position' => 'President', 'display_order' => 1]);
        Executive::create(['name' => 'Hidden', 'position' => 'Ex-officio', 'display_order' => 0, 'is_visible' => false]);

        $this->getJson('/api/v1/public/home')
            ->assertOk()
            ->assertJsonCount(2, 'data.executives')
            ->assertJsonPath('data.executives.0.name', 'First')
            ->assertJsonPath('data.executives.1.name', 'Second');
    }

    public function test_member_cannot_manage_homepage_content(): void
    {
        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/admin/home-content')
            ->assertForbidden();

        $this->actingAs($member->user, 'sanctum')
            ->patchJson('/api/v1/admin/home-content', ['hero_title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_committee_officer_cannot_manage_homepage_content(): void
    {
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->patchJson('/api/v1/admin/home-content', ['hero_title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_finance_officer_cannot_manage_homepage_content(): void
    {
        $officer = $this->userWithRole('finance_officer');

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/admin/home-content')
            ->assertForbidden();
    }
}
