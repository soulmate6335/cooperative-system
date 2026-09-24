<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class NoticeManagementTest extends TestCase
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

    private function noticePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Annual General Meeting',
            'body' => 'The annual general meeting will hold at the cooperative hall.',
            'excerpt' => 'Upcoming AGM',
            'visibility' => 'public',
        ], $overrides);
    }

    private function createNotice(array $overrides = []): Notice
    {
        return Notice::create($this->noticePayload($overrides));
    }

    public function test_admin_can_create_a_notice_as_a_draft(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/notices', $this->noticePayload());

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Annual General Meeting')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.visibility', 'public');

        $this->assertDatabaseHas('notices', ['title' => 'Annual General Meeting', 'status' => 'draft']);
    }

    public function test_admin_can_edit_a_notice_without_changing_its_status(): void
    {
        $notice = $this->createNotice(['status' => 'published', 'publish_at' => now()]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/notices/'.$notice->id, $this->noticePayload(['title' => 'Updated title', 'body' => 'Updated body.']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'title' => 'Updated title', 'status' => 'published']);
    }

    public function test_admin_can_publish_a_notice(): void
    {
        $notice = $this->createNotice();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/notices/'.$notice->id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'status' => 'published']);
        $this->assertNotNull($notice->fresh()->publish_at);
    }

    public function test_admin_can_archive_a_notice(): void
    {
        $notice = $this->createNotice(['status' => 'published', 'publish_at' => now()]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/notices/'.$notice->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'status' => 'archived']);
    }

    public function test_public_published_notice_is_visible_and_detail_is_public(): void
    {
        $notice = $this->createNotice(['status' => 'published', 'publish_at' => now()]);

        $this->getJson('/api/v1/notices')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notice->id)
            ->assertJsonPath('data.0.title', $notice->title);

        $this->getJson('/api/v1/notices/'.$notice->id)
            ->assertOk()
            ->assertJsonPath('data.body', $notice->body);
    }

    public function test_member_only_notice_is_visible_to_members_but_not_publicly(): void
    {
        $notice = $this->createNotice([
            'status' => 'published',
            'publish_at' => now(),
            'visibility' => 'members',
        ]);

        $public = $this->getJson('/api/v1/notices')->assertOk();
        $this->assertCount(0, $public->json('data'));

        $this->getJson('/api/v1/notices/'.$notice->id)->assertNotFound();

        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/member/notices')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notice->id);
    }

    public function test_future_scheduled_notice_is_hidden_everywhere(): void
    {
        $notice = $this->createNotice([
            'status' => 'published',
            'publish_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/notices')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notices/'.$notice->id)->assertNotFound();

        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/member/notices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_expired_notice_is_hidden_everywhere(): void
    {
        $notice = $this->createNotice([
            'status' => 'published',
            'publish_at' => now()->subDays(5),
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/notices')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notices/'.$notice->id)->assertNotFound();

        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/member/notices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_draft_notice_is_never_exposed_publicly(): void
    {
        $notice = $this->createNotice(['status' => 'draft']);

        $this->getJson('/api/v1/notices')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notices/'.$notice->id)->assertNotFound();

        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/member/notices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_archived_notice_is_hidden_everywhere(): void
    {
        $notice = $this->createNotice(['status' => 'archived']);

        $this->getJson('/api/v1/notices')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notices/'.$notice->id)->assertNotFound();

        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/member/notices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_member_cannot_access_admin_notices(): void
    {
        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/admin/notices')
            ->assertForbidden();

        $this->actingAs($member->user, 'sanctum')
            ->postJson('/api/v1/admin/notices', $this->noticePayload())
            ->assertForbidden();
    }

    public function test_committee_officer_cannot_manage_notices(): void
    {
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/admin/notices', $this->noticePayload())
            ->assertForbidden();

        $notice = $this->createNotice();
        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/admin/notices/'.$notice->id.'/publish')
            ->assertForbidden();
    }

    public function test_expiry_cannot_precede_the_publish_date(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/notices', $this->noticePayload([
                'publish_at' => '2026-10-01 09:00:00',
                'expires_at' => '2026-09-30 09:00:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');
    }

    public function test_publishing_a_members_notice_notifies_active_members_once(): void
    {
        $firstMember = $this->loanMember();
        $secondMember = $this->loanMember();
        $officer = $this->userWithRole('committee_officer');

        $notice = $this->createNotice(['visibility' => 'members']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/notices/'.$notice->id.'/publish')
            ->assertOk();

        $recipients = [$firstMember->user_id, $secondMember->user_id];

        $this->assertSame(2, $this->notificationCountFor($recipients));
        $this->assertSame(0, $this->notificationCountFor([$officer->id]));

        // Editing the published notice must not flood members with duplicates.
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/notices/'.$notice->id, $this->noticePayload(['title' => 'Edited after publish']))
            ->assertOk();

        $this->assertSame(2, $this->notificationCountFor($recipients));
    }

    public function test_authenticated_routes_require_a_sanctum_session(): void
    {
        $this->getJson('/api/v1/admin/notices')->assertUnauthorized();
        $this->getJson('/api/v1/member/notices')->assertUnauthorized();
    }

    private function notificationCountFor(array $userIds): int
    {
        return DB::table('notifications')
            ->whereIn('notifiable_id', $userIds)
            ->count();
    }
}
