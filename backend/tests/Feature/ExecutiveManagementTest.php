<?php

namespace Tests\Feature;

use App\Models\Executive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class ExecutiveManagementTest extends TestCase
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

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'executive.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            'image/png',
        );
    }

    private function executivePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Amina Yusuf',
            'position' => 'President',
            'biography' => 'Leading the cooperative since 2021.',
            'display_order' => 1,
        ], $overrides);
    }

    public function test_admin_can_list_executives(): void
    {
        Executive::create($this->executivePayload());
        Executive::create($this->executivePayload(['name' => 'Ibrahim Musa', 'position' => 'Treasurer', 'display_order' => 2]));

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/executives')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_create_an_executive_without_a_photo(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/executives', $this->executivePayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Amina Yusuf')
            ->assertJsonPath('data.position', 'President')
            ->assertJsonPath('data.is_visible', true)
            ->assertJsonPath('data.display_order', 1)
            ->assertJsonPath('data.photo_url', null);

        $this->assertDatabaseHas('executives', ['name' => 'Amina Yusuf', 'position' => 'President']);
    }

    public function test_admin_can_create_an_executive_with_a_photo(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->post('/api/v1/admin/executives', $this->executivePayload() + ['photo' => $this->photo()])
            ->assertCreated();

        $photoUrl = $response->json('data.photo_url');
        $this->assertNotNull($photoUrl);
        $this->assertStringContainsString('/storage/executives/', $photoUrl);

        // The internal filesystem path must never leak into the response.
        $response->assertJsonMissingPath('data.photo_path');

        $executive = Executive::firstOrFail();
        $this->assertNotNull($executive->photo_path);
        $this->assertStringContainsString('executives/', $executive->photo_path);
        Storage::disk('public')->assertExists($executive->photo_path);
    }

    public function test_photo_validation_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin(), 'sanctum')
            ->post('/api/v1/admin/executives', $this->executivePayload() + ['photo' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('executives', 0);
    }

    public function test_photo_validation_rejects_oversized_files(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('large.png', str_repeat('0', 3 * 1024 * 1024), 'image/png');

        $this->actingAs($this->admin(), 'sanctum')
            ->post('/api/v1/admin/executives', $this->executivePayload() + ['photo' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_admin_can_update_an_executive_and_replace_its_photo(): void
    {
        Storage::fake('public');

        $executive = Executive::create($this->executivePayload() + ['photo_path' => 'executives/old.png']);

        $this->actingAs($this->admin(), 'sanctum')
            ->patch('/api/v1/admin/executives/'.$executive->id, $this->executivePayload([
                'name' => 'Amina Y. Musa',
                'position' => 'President (reelected)',
                'photo' => $this->photo(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Amina Y. Musa');

        $fresh = $executive->fresh();
        $this->assertNotSame('executives/old.png', $fresh->photo_path);
        Storage::disk('public')->assertMissing('executives/old.png');
        Storage::disk('public')->assertExists($fresh->photo_path);
    }

    public function test_admin_can_toggle_executive_visibility(): void
    {
        $executive = Executive::create($this->executivePayload());

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/executives/'.$executive->id.'/visibility', ['is_visible' => false])
            ->assertOk()
            ->assertJsonPath('data.is_visible', false);

        $this->assertDatabaseHas('executives', ['id' => $executive->id, 'is_visible' => false]);
    }

    public function test_public_endpoint_returns_only_visible_executives_in_order(): void
    {
        Executive::create($this->executivePayload(['name' => 'Third', 'display_order' => 3]));
        Executive::create($this->executivePayload(['name' => 'Hidden', 'display_order' => 0, 'is_visible' => false]));
        Executive::create($this->executivePayload(['name' => 'First', 'display_order' => 1]));
        Executive::create($this->executivePayload(['name' => 'Second', 'display_order' => 2]));

        $response = $this->getJson('/api/v1/executives')->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['First', 'Second', 'Third'], $names);
    }

    public function test_public_executive_payload_never_exposes_internal_fields(): void
    {
        $executive = Executive::create($this->executivePayload());

        $this->getJson('/api/v1/executives')
            ->assertOk()
            ->assertJsonPath('data.0.name', $executive->name)
            ->assertJsonMissingPath('data.0.is_visible')
            ->assertJsonMissingPath('data.0.photo_path')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.created_at');
    }

    public function test_member_cannot_manage_executives(): void
    {
        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/admin/executives')
            ->assertForbidden();

        $this->actingAs($member->user, 'sanctum')
            ->postJson('/api/v1/admin/executives', $this->executivePayload())
            ->assertForbidden();
    }

    public function test_committee_officer_cannot_manage_executives(): void
    {
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/admin/executives', $this->executivePayload())
            ->assertForbidden();
    }
}
