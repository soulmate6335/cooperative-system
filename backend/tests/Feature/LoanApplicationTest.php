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

    public function test_submission_is_blocked_while_eligibility_decision_is_pending(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMemberJoinedAt(now()->subMonths(5)->subDays(29));
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eligibility');
    }

    public function test_submission_is_blocked_while_administratively_ineligible(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);
        $this->committeeMeeting();
        $this->eligibleDecision($member, $product, 'ineligible', 'Insufficient savings history.');

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eligibility');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'draft']);
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
        $this->eligibleDecision($member, $product);

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
        $this->eligibleDecision($member, $product);

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
        $this->eligibleDecision($member, $product);
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

    public function test_inactive_member_cannot_create_an_application(): void
    {
        $member = $this->loanMember('inactive');
        $product = $this->loanProduct();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'School fees',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('member');
    }

    public function test_suspended_member_cannot_create_an_application(): void
    {
        $member = $this->loanMember('suspended');
        $product = $this->loanProduct();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'School fees',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('member');
    }

    public function test_member_can_list_their_own_loan_applications(): void
    {
        $member = $this->loanMember();
        $this->draftApplication($member, $this->loanProduct());

        $this->actingAs($member->user, 'sanctum')->getJson('/api/v1/member/loans/applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_member_cannot_view_another_members_application(): void
    {
        $member = $this->loanMember();
        $other = $this->loanMember();
        $application = $this->draftApplication($member, $this->loanProduct());

        $this->actingAs($other->user, 'sanctum')->getJson('/api/v1/member/loans/applications/'.$application->id)
            ->assertForbidden();
    }

    public function test_member_can_update_their_own_draft(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['minimum_amount_minor' => 100000, 'maximum_amount_minor' => 2000000]);
        $application = $this->draftApplication($member, $product, 500000);

        $this->actingAs($member->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'amount_requested_minor' => 750000,
            'purpose' => 'Tuition fees',
        ])->assertOk()
            ->assertJsonPath('data.amount_requested_minor', 750000)
            ->assertJsonPath('data.purpose', 'Tuition fees')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'amount_requested_minor' => 750000, 'purpose' => 'Tuition fees']);
    }

    public function test_member_can_switch_the_product_of_their_own_draft(): void
    {
        $member = $this->loanMember();
        $first = $this->loanProduct();
        $second = $this->loanProduct();
        $application = $this->draftApplication($member, $first);

        $this->actingAs($member->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'loan_product_id' => $second->id,
        ])->assertOk()
            ->assertJsonPath('data.loan_product.id', $second->id);

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'loan_product_id' => $second->id]);
    }

    public function test_a_member_cannot_update_another_members_draft(): void
    {
        $member = $this->loanMember();
        $other = $this->loanMember();
        $application = $this->draftApplication($member, $this->loanProduct());

        $this->actingAs($other->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'amount_requested_minor' => 600000,
        ])->assertForbidden();
    }

    public function test_a_submitted_application_cannot_be_updated(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $this->actingAs($member->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'purpose' => 'Changed after submission',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('application');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'purpose' => 'Salary advance', 'status' => 'submitted']);
    }

    public function test_updating_a_draft_rejects_an_amount_below_the_product_minimum(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['minimum_amount_minor' => 100000]);
        $application = $this->draftApplication($member, $product, 500000);

        $this->actingAs($member->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'amount_requested_minor' => 50000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount_requested_minor');
    }

    public function test_updating_a_draft_rejects_an_amount_above_the_product_maximum(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['maximum_amount_minor' => 1000000]);
        $application = $this->draftApplication($member, $product, 500000);

        $this->actingAs($member->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'amount_requested_minor' => 1500000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount_requested_minor');
    }

    public function test_updating_a_draft_with_a_deactivated_product_is_rejected(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->draftApplication($member, $product);

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->postJson('/api/v1/admin/loan-products/'.$product->id.'/deactivate')->assertOk();

        $this->actingAs($member->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'loan_product_id' => $product->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('loan_product_id');
    }

    public function test_suspended_member_cannot_update_a_draft(): void
    {
        $member = $this->loanMember();
        $application = $this->draftApplication($member, $this->loanProduct());

        $member->update(['status' => 'suspended']);

        $this->actingAs($member->user, 'sanctum')->patchJson('/api/v1/member/loans/applications/'.$application->id, [
            'purpose' => 'Edited while suspended',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('member');
    }

    public function test_second_submission_is_rejected(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'submitted']);
    }

    public function test_submission_is_blocked_when_the_amount_exceeds_the_updated_product_maximum(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['maximum_amount_minor' => 2000000]);
        $application = $this->draftApplication($member, $product, 1500000);
        $this->committeeMeeting();
        $this->eligibleDecision($member, $product);

        // The product bounds change after the draft was created; submission
        // must revalidate against the current configuration.
        $this->actingAs($this->userWithRole('admin'), 'sanctum')->patchJson('/api/v1/admin/loan-products/'.$product->id, [
            'maximum_amount_minor' => 1000000,
        ])->assertOk();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_requested_minor');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'draft']);
    }
}
