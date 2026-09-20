<?php

namespace Tests\Feature;

use App\Services\LoanApplicationService;
use App\Services\LoanGuarantorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class LoanApplicationTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_member_can_create_a_draft_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();

        $response = $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'School fees',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.amount_requested_minor', 500000);

        $this->assertDatabaseHas('loan_applications', ['member_id' => $member->id, 'loan_product_id' => $product->id, 'status' => 'draft']);
    }

    public function test_user_without_apply_permission_cannot_create_application(): void
    {
        $user = $this->userWithRole('committee_officer');
        $product = $this->loanProduct();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'School fees',
        ])->assertForbidden();
    }

    public function test_amount_below_product_minimum_is_rejected(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['minimum_amount_minor' => 100000]);

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 50000,
            'purpose' => 'School fees',
        ])->assertUnprocessable();
    }

    public function test_inactive_product_cannot_be_selected_for_a_new_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['status' => 'inactive']);

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'School fees',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('loan_product_id');
    }

    public function test_only_one_open_application_is_allowed_per_member(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $this->draftApplication($member, $product);

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'Another purpose',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('application');
    }

    public function test_new_application_is_allowed_after_a_terminal_state(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $first = $this->draftApplication($member, $product);

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$first->id.'/cancel')->assertOk();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'Replacement application',
        ])->assertCreated();
    }

    public function test_submission_requires_eligible_membership_length(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMemberJoinedAt(now()->subMonths(5)->subDays(29));
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')->assertUnprocessable();
    }

    public function test_submission_of_a_draft_with_deactivated_product_is_blocked(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loan-products/'.$product->id.'/deactivate')->assertOk();

        $response = $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('loan_product_id');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'draft', 'loan_product_id' => $product->id]);
    }

    public function test_submission_binds_the_earliest_eligible_committee_meeting(): void
    {
        $this->travelTo('2026-01-01 12:00:00');
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);

        $soon = $this->committeeMeeting(16, 14);
        $later = $this->committeeMeeting(30, 14);

        $application = app(LoanApplicationService::class)->submit($application);

        $this->assertSame($soon->id, $application->committee_meeting_id);
        $this->assertNotNull($application->submitted_at);
    }

    public function test_submission_is_blocked_when_no_meeting_satisfies_the_cutoff(): void
    {
        $this->travelTo('2026-01-01 12:00:00');
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting(10, 14);

        $response = $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('committee_meeting');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'draft', 'committee_meeting_id' => null]);
    }

    public function test_suspended_member_cannot_submit_an_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();

        $member->update(['status' => 'suspended']);

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'draft']);
    }

    public function test_admin_can_cancel_the_application_of_a_suspended_member(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $member->update(['status' => 'suspended']);

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loans/applications/'.$application->id.'/cancel', ['reason' => 'Membership suspended.'])
            ->assertOk();

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'cancelled']);
    }

    public function test_suspended_member_cannot_cancel_their_own_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);

        $member->update(['status' => 'suspended']);

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/cancel')
            ->assertUnprocessable();
    }

    public function test_member_can_cancel_their_own_draft(): void
    {
        $member = $this->loanMember();
        $application = $this->draftApplication($member, $this->loanProduct());

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/cancel')
            ->assertOk();

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'cancelled']);
    }

    public function test_member_cannot_cancel_another_members_application(): void
    {
        $member = $this->loanMember();
        $other = $this->loanMember();
        $application = $this->draftApplication($member, $this->loanProduct());

        $this->actingAs($other->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/cancel')
            ->assertForbidden();
    }

    public function test_submission_snapshots_savings_and_shares_balances(): void
    {
        $member = $this->loanMember();
        $this->seedBalance($member, 'savings', 200000);
        $this->seedBalance($member, 'shares', 75000);
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();

        $application = app(LoanApplicationService::class)->submit($application);

        $this->assertSame(200000, $application->savings_balance_minor);
        $this->assertSame(75000, $application->shares_balance_minor);
    }

    public function test_submitted_application_status_reflects_guarantor_stage(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $this->assertSame('submitted', $application->status);

        $guarantor = $this->loanMember();
        $request = app(LoanGuarantorService::class)->request($application, $member, $guarantor->id);

        $this->assertSame('awaiting_guarantors', $application->fresh()->status);
        $this->assertSame('pending', $request->status);
    }

    public function test_service_rejects_create_when_application_number_collides(): void
    {
        $member = $this->loanMember();
        $this->draftApplication($member, $this->loanProduct());

        try {
            $this->draftApplication($member, $this->loanProduct());
            $this->fail('A second open application should be rejected by the service.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application', $exception->errors());
        }

        $this->assertDatabaseCount('loan_applications', 1);
    }

    public function test_application_model_cannot_be_deleted(): void
    {
        $member = $this->loanMember();
        $application = $this->draftApplication($member, $this->loanProduct());

        $this->expectException(\LogicException::class);
        $application->delete();
    }
}
