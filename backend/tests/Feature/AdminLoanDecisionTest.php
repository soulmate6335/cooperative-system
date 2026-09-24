<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\LoanDecision;
use App\Models\Member;
use App\Models\User;
use App\Services\LoanApplicationService;
use App\Services\LoanDecisionService;
use App\Services\LoanGuarantorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class AdminLoanDecisionTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------ helpers

    private function pendingApplication(): LoanApplication
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();

        return $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function superAdmin(): User
    {
        return $this->userWithRole('super_admin');
    }

    // -------------------------------------------- authorization: queue

    public function test_admin_can_view_the_decision_queue(): void
    {
        $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_super_admin_can_view_the_decision_queue(): void
    {
        $this->pendingApplication();

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_committee_officer_cannot_view_the_decision_queue(): void
    {
        $this->pendingApplication();

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertForbidden();
    }

    public function test_member_cannot_view_the_decision_queue(): void
    {
        $this->pendingApplication();

        $this->actingAs($this->userWithRole('member'), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertForbidden();
    }

    public function test_finance_officer_cannot_view_the_decision_queue(): void
    {
        $this->pendingApplication();

        $this->actingAs($this->userWithRole('finance_officer'), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertForbidden();
    }

    // ------------------------------------------------------------ queue

    public function test_queue_includes_pending_admin_decision_applications(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $application->id)
            ->assertJsonPath('data.0.status', 'pending_admin_decision')
            ->assertJsonPath('data.0.investigation.recommendation', 'Recommend approval based on findings.')
            ->assertJsonPath('data.0.investigation.submitted_at', $application->investigation->submitted_at->toISOString());
    }

    public function test_queue_excludes_draft_applications(): void
    {
        $this->draftApplication($this->loanMember(), $this->loanProduct());

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_submitted_applications(): void
    {
        $this->submitApplication($this->draftApplication($this->loanMember(), $this->loanProduct()));

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_awaiting_guarantors_applications(): void
    {
        $member = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($member, $this->loanProduct()));
        $guarantorMember = $this->loanMember();
        app(LoanGuarantorService::class)->request($application, $member, $guarantorMember->id);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_guarantors_confirmed_applications(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $this->acceptedApplication($member, $product, $product->required_guarantors);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_under_investigation_applications(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->acceptedApplication($member, $product, $product->required_guarantors);
        $this->assignedInvestigation($application, $this->userWithRole('committee_officer'));

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_approved_applications(): void
    {
        $this->approveApplication($this->pendingApplication(), $this->admin());

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_rejected_applications(): void
    {
        app(LoanDecisionService::class)->reject($this->pendingApplication(), $this->admin(), 'Does not meet the lending criteria.');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_cancelled_applications(): void
    {
        app(LoanApplicationService::class)->cancelByAdmin($this->pendingApplication(), 'Cancelled by admin.');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_applications_without_a_submitted_investigation(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->acceptedApplication($member, $product, $product->required_guarantors);
        $application->update(['status' => 'pending_admin_decision']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_applications_for_suspended_members(): void
    {
        $application = $this->pendingApplication();
        $application->member->update(['status' => 'suspended']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_queue_search_filters_by_applicant_name(): void
    {
        $firstMember = $this->loanMember();
        $firstMember->user->update(['name' => 'Alpha Applicant']);
        $first = $this->pendingDecisionApplication($firstMember, $this->loanProduct(), $this->userWithRole('committee_officer'));

        $secondMember = $this->loanMember();
        $secondMember->user->update(['name' => 'Beta Borrower']);
        $this->pendingDecisionApplication($secondMember, $this->loanProduct(), $this->userWithRole('committee_officer'));

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions?search=Alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.member.name', 'Alpha Applicant');
    }

    public function test_queue_filters_by_product(): void
    {
        $firstProduct = $this->loanProduct();
        $first = $this->pendingDecisionApplication($this->loanMember(), $firstProduct, $this->userWithRole('committee_officer'));
        $this->pendingDecisionApplication($this->loanMember(), $this->loanProduct(), $this->userWithRole('committee_officer'));

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions?product_id='.$firstProduct->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id);
    }

    public function test_queue_filters_by_meeting(): void
    {
        $first = $this->pendingApplication();
        $second = $this->pendingApplication();

        // Submission may bind both applications to the same candidate meeting,
        // so pin the second one to a distinct meeting to make the filter meaningful.
        $second->update(['committee_meeting_id' => $this->committeeMeeting()->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions?meeting_id='.$first->committee_meeting_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id);
    }

    // ------------------------------------------------------------ detail

    public function test_admin_can_view_the_complete_decision_detail(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $officer = $this->userWithRole('committee_officer');
        $application = $this->pendingDecisionApplication($member, $product, $officer);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.application.id', $application->id)
            ->assertJsonPath('data.application.status', 'pending_admin_decision')
            ->assertJsonPath('data.application.member.name', $member->user->name)
            ->assertJsonPath('data.application.member.member_number', $member->member_number)
            ->assertJsonPath('data.application.loan_product.name', $product->name)
            ->assertJsonPath('data.application.investigation.assignee.id', $officer->id)
            ->assertJsonPath('data.application.investigation.assignee.name', $officer->name)
            ->assertJsonPath('data.application.decision', null)
            ->assertJsonPath('data.application.loan', null)
            ->assertJsonPath('data.eligibility.decision.status', 'eligible')
            ->assertJsonPath('data.eligibility.factors.member_active', true);
    }

    public function test_super_admin_can_view_the_decision_detail(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/v1/admin/loan-decisions/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.application.id', $application->id);
    }

    public function test_unauthorized_users_cannot_view_internal_decision_detail(): void
    {
        $application = $this->pendingApplication();
        $roles = ['committee_officer', 'member', 'finance_officer'];

        foreach ($roles as $role) {
            $this->actingAs($this->userWithRole($role), 'sanctum')
                ->getJson('/api/v1/admin/loan-decisions/'.$application->id)
                ->assertForbidden();
        }
    }

    // ---------------------------------------------------------- approval

    public function test_admin_can_approve_a_valid_pending_application(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk()
            ->assertJsonPath('message', 'Loan application approved successfully.');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->assertDatabaseHas('loan_decisions', ['loan_application_id' => $application->id, 'decision' => 'approved']);
    }

    public function test_approval_creates_exactly_one_loan(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk();

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
            'member_id' => $application->member_id,
            'status' => 'pending_disbursement',
        ]);
    }

    public function test_approval_creates_exactly_one_immutable_loan_decision(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk();

        $this->assertDatabaseCount('loan_decisions', 1);
        $decision = LoanDecision::where('loan_application_id', $application->id)->firstOrFail();

        $this->expectException(LogicException::class);
        $decision->update(['decision_reason' => 'Attempted edit of a recorded decision.']);
    }

    public function test_approved_terms_are_snapshotted(): void
    {
        $application = $this->pendingApplication();
        $product = $application->product;

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms([
                'approved_amount_minor' => 450000,
                'interest_rate_basis_points' => 1200,
                'interest_method' => 'flat',
                'repayment_months' => 9,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
            'principal_amount_minor' => 450000,
            'interest_rate_basis_points' => 1200,
            'interest_method' => 'flat',
            'repayment_months' => 9,
        ]);

        // Later product edits never alter the approved snapshot.
        $product->update([
            'interest_rate_basis_points' => 2000,
            'interest_method' => 'reducing_balance',
            'repayment_months' => 24,
            'minimum_amount_minor' => 90000,
            'maximum_amount_minor' => 900000,
        ]);

        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
            'principal_amount_minor' => 450000,
            'interest_rate_basis_points' => 1200,
            'interest_method' => 'flat',
            'repayment_months' => 9,
        ]);
    }

    public function test_approval_does_not_create_a_payment(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_approval_does_not_create_a_financial_transaction(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk();

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_approval_does_not_create_a_disbursement(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk();

        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
            'status' => 'pending_disbursement',
            'disbursed_at' => null,
            'disbursed_amount_minor' => null,
        ]);
    }

    public function test_approval_requires_a_decision_reason(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms(['decision_reason' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision_reason');
    }

    public function test_approval_validates_the_approved_amount(): void
    {
        $application = $this->pendingApplication();

        // Below the product minimum of 10,000 minor units.
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms(['approved_amount_minor' => 5000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approved_amount_minor');
    }

    public function test_approval_validates_the_interest_rate(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms(['interest_rate_basis_points' => -100]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('interest_rate_basis_points');
    }

    public function test_approval_validates_repayment_months(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms(['repayment_months' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('repayment_months');
    }

    public function test_approval_requires_a_current_eligible_eligibility_decision(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));

        // A later eligibility review revoked the member's eligibility; the
        // append-only history means the current decision is ineligible.
        $this->eligibleDecision($member, $product, 'ineligible', 'The member no longer meets the lending criteria.');

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eligibility');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'pending_admin_decision']);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_approval_requires_a_submitted_investigation(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->acceptedApplication($member, $product, $product->required_guarantors);
        $application->update(['status' => 'pending_admin_decision']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_approval_requires_an_active_member(): void
    {
        $application = $this->pendingApplication();
        $application->member->update(['status' => 'suspended']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_approval_cannot_happen_twice(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('loan_decisions', 1);
    }

    public function test_approval_service_revalidates_terms_server_side(): void
    {
        $application = $this->pendingApplication();
        $admin = $this->admin();

        $service = app(LoanDecisionService::class);

        $cases = [
            'interest_rate_basis_points' => -5,
            'repayment_months' => 0,
            'interest_method' => 'compound',
        ];

        foreach ($cases as $field => $invalidValue) {
            try {
                $service->approve($application, $admin, $this->approvalTerms([$field => $invalidValue]));
                $this->fail('Expected a ValidationException for '.$field);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_decisions', 0);
    }

    // ---------------------------------------------------------- rejection

    public function test_admin_can_reject_a_valid_pending_application(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'The application does not meet the lending criteria.'])
            ->assertOk()
            ->assertJsonPath('message', 'Loan application rejected successfully.');

        $this->assertDatabaseHas('loan_applications', [
            'id' => $application->id,
            'status' => 'rejected',
            'rejection_reason' => 'The application does not meet the lending criteria.',
        ]);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_rejection_creates_a_loan_decision(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertOk();

        $this->assertDatabaseCount('loan_decisions', 1);
        $this->assertDatabaseHas('loan_decisions', [
            'loan_application_id' => $application->id,
            'decision' => 'rejected',
            'decision_reason' => 'Not approved.',
        ]);
    }

    public function test_rejection_does_not_create_a_loan(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertOk();

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_rejection_does_not_create_a_payment(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertOk();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_rejection_does_not_create_a_financial_transaction(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertOk();

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_a_rejected_application_cannot_later_be_approved(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_decisions', 1);
    }

    public function test_a_rejected_application_is_terminal(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'A second rejection attempt.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'rejected']);
        $this->assertDatabaseCount('loan_decisions', 1);
    }

    public function test_rejection_via_the_decision_endpoint_requires_pending_admin_decision(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->acceptedApplication($member, $product, $product->required_guarantors);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'guarantors_confirmed']);
        $this->assertDatabaseCount('loan_decisions', 0);
    }

    // ---------------------------------------------------------- security

    public function test_committee_officer_cannot_approve_a_loan(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_committee_officer_cannot_reject_a_loan(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertForbidden();

        $this->assertDatabaseCount('loan_decisions', 0);
    }

    public function test_a_member_cannot_approve_a_loan(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->userWithRole('member'), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_a_member_cannot_reject_a_loan(): void
    {
        $application = $this->pendingApplication();

        $this->actingAs($this->userWithRole('member'), 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/reject', ['reason' => 'Not approved.'])
            ->assertForbidden();

        $this->assertDatabaseCount('loan_decisions', 0);
    }

    public function test_a_second_conflicting_decision_is_rejected(): void
    {
        $application = $this->pendingApplication();
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertOk();

        // A conflicting second decision (approval after an approval) is refused
        // at the authorization boundary and never duplicates the loan.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loan-decisions/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('loan_decisions', 1);
    }
}
