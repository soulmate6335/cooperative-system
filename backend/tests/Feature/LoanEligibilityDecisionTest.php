<?php

namespace Tests\Feature;

use App\Models\LoanEligibilityDecision;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\LoanEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

/**
 * Administrative loan eligibility authority.
 *
 * The system calculates eligibility factors, but the administrator is the
 * authority that grants or denies the right to apply. These tests pin the
 * decision workflow, the override semantics and the data-integrity rules.
 */
class LoanEligibilityDecisionTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_member_can_view_their_eligibility_information(): void
    {
        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct();

        $response = $this->actingAs($member->user, 'sanctum')->getJson('/api/v1/member/loans/eligibility?product_id='.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.membership_months', 6)
            ->assertJsonPath('data.admin_decision.status', 'pending');
    }

    public function test_member_endpoint_distinguishes_system_assessment_from_admin_decision(): void
    {
        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct();
        $this->eligibleDecision($member, $product, 'ineligible', 'Documentation verification pending.');

        $response = $this->actingAs($member->user, 'sanctum')->getJson('/api/v1/member/loans/eligibility?product_id='.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.eligible', true) // system assessment
            ->assertJsonPath('data.admin_decision.status', 'ineligible'); // authoritative decision
    }

    public function test_member_cannot_decide_their_own_eligibility(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
        ])->assertForbidden();
    }

    public function test_unauthorized_user_cannot_make_eligibility_decisions(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
        ])->assertForbidden();
    }

    public function test_authorized_admin_can_mark_a_member_eligible(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.admin_decision.status', 'eligible');

        $this->assertDatabaseHas('loan_eligibility_decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
            'decided_by' => $admin->id,
        ]);
    }

    public function test_authorized_admin_can_mark_a_member_ineligible(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'ineligible',
            'reason' => 'Insufficient savings history.',
        ])->assertCreated()
            ->assertJsonPath('data.admin_decision.status', 'ineligible');

        $this->assertDatabaseHas('loan_eligibility_decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'ineligible',
        ]);
    }

    public function test_decision_records_the_responsible_admin(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
        ])->assertCreated();

        $decision = LoanEligibilityDecision::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame($admin->id, $decision->decided_by);
        $this->assertSame($admin->id, $decision->fresh()->decidedBy->id);
    }

    public function test_decision_records_timestamp(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMember();
        $product = $this->loanProduct();

        $response = $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
        ])->assertCreated();

        $response->assertJsonPath('data.admin_decision.decided_at', now()->toISOString());
        $this->assertDatabaseHas('loan_eligibility_decisions', [
            'member_id' => $member->id,
            'decided_at' => '2026-06-01 12:00:00',
        ]);
    }

    public function test_override_requires_a_reason(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMemberJoinedAt(now()->subMonths(4));
        $product = $this->loanProduct();

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_override_with_reason_is_recorded(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMemberJoinedAt(now()->subMonths(4));
        $product = $this->loanProduct();

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
            'reason' => 'Manager discretion for long-standing contributor.',
        ])->assertCreated()
            ->assertJsonPath('data.admin_decision.status', 'eligible');

        $this->assertDatabaseHas('loan_eligibility_decisions', [
            'member_id' => $member->id,
            'status' => 'eligible',
            'reason' => 'Manager discretion for long-standing contributor.',
        ]);
    }

    public function test_override_does_not_modify_membership_duration(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMemberJoinedAt(now()->subMonths(4));
        $joinedAt = $member->joined_at;
        $product = $this->loanProduct();

        $this->eligibleDecision($member, $product, 'eligible', 'Manager discretion.');

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertSame(4, $factors['membership_months']);
        $this->assertSame($joinedAt->toISOString(), $member->fresh()->joined_at->toISOString());
    }

    public function test_override_does_not_modify_savings(): void
    {
        $member = $this->loanMemberJoinedAt(now()->subMonths(4));
        $this->seedBalance($member, 'savings', 150000);
        $product = $this->loanProduct();

        $this->eligibleDecision($member, $product, 'eligible', 'Manager discretion.');

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertSame(150000, $factors['savings_balance_minor']);
        $this->assertFalse($factors['eligible']);
    }

    public function test_override_does_not_modify_shares(): void
    {
        $member = $this->loanMemberJoinedAt(now()->subMonths(4));
        $this->seedBalance($member, 'shares', 50000);
        $product = $this->loanProduct();

        $this->eligibleDecision($member, $product, 'eligible', 'Manager discretion.');

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertSame(50000, $factors['shares_balance_minor']);
        $this->assertFalse($factors['eligible']);
    }

    public function test_member_cannot_submit_an_application_while_eligibility_is_pending(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eligibility');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'draft']);
    }

    public function test_member_cannot_submit_an_application_while_administratively_ineligible(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();
        $this->eligibleDecision($member, $product, 'ineligible', 'Pending documentation checks.');

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eligibility');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'draft']);
    }

    public function test_member_can_submit_an_application_when_administratively_eligible(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();
        $this->eligibleDecision($member, $product, 'eligible');

        $response = $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit');

        $response->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertNotNull($response->json('data.submitted_at'));
    }

    public function test_admin_override_allows_submission_when_system_factors_are_not_met(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMemberJoinedAt(now()->subMonths(4));
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();
        $this->eligibleDecision($member, $product, 'eligible', 'Manager discretion for long-standing contributor.');

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_eligibility_approval_does_not_create_a_loan_application_or_loan(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loan-eligibility/decisions', [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'status' => 'eligible',
        ])->assertCreated();

        $this->assertDatabaseCount('loan_applications', 0);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_admin_cannot_decide_their_own_eligibility(): void
    {
        $member = $this->loanMember();
        $admin = $member->user;
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $product = $this->loanProduct();

        try {
            app(LoanEligibilityService::class)->recordDecision($member, $product, $admin, 'eligible');
            $this->fail('An administrator must not decide their own eligibility.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('member_id', $exception->errors());
        }

        $this->assertDatabaseCount('loan_eligibility_decisions', 0);
    }

    public function test_decision_history_is_preserved_across_reviews(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $this->eligibleDecision($member, $product, 'ineligible', 'First review.');
        $this->eligibleDecision($member, $product, 'eligible', 'Second review.');

        $this->assertDatabaseCount('loan_eligibility_decisions', 2);
        $this->assertSame('eligible', app(LoanEligibilityService::class)->currentDecisionStatusFor($member, $product));
    }

    public function test_decision_model_cannot_be_updated_or_deleted(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $decision = $this->eligibleDecision($member, $product, 'ineligible');

        try {
            $decision->update(['status' => 'eligible']);
            $this->fail('Decisions must be immutable.');
        } catch (\LogicException) {
            // expected
        }

        $this->expectException(\LogicException::class);
        $decision->delete();
    }

    public function test_authorized_admin_can_list_members_for_eligibility_review(): void
    {
        $first = $this->loanMember();
        $second = $this->loanMember();

        $response = $this->actingAs($this->userWithRole('admin'), 'sanctum')->getJson('/api/v1/admin/loan-eligibility/members');

        $response->assertOk();
        $memberIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($memberIds->contains($first->id));
        $this->assertTrue($memberIds->contains($second->id));
        $this->assertSame($first->user->name, collect($response->json('data'))->firstWhere('id', $first->id)['name']);
    }

    public function test_member_cannot_list_members_for_eligibility_review(): void
    {
        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')->getJson('/api/v1/admin/loan-eligibility/members')->assertForbidden();
    }

    public function test_authorized_admin_can_view_an_eligibility_assessment(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $this->eligibleDecision($member, $product, 'ineligible', 'Reviewed by committee.');

        $response = $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->getJson('/api/v1/admin/loan-eligibility/assessments?member_id='.$member->id.'&product_id='.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.factors.eligible', true)
            ->assertJsonPath('data.factors.admin_decision.status', 'ineligible');
    }
}