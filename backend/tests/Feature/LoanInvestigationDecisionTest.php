<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\Role;
use App\Services\LoanDecisionService;
use App\Services\LoanProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class LoanInvestigationDecisionTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function confirmedApplication(Member $member, LoanProduct $product)
    {
        return $this->acceptedApplication($member, $product, $product->required_guarantors);
    }

    public function test_admin_can_assign_an_investigation_to_a_committee_officer(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $response = $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/assign', ['assigned_to' => $officer->id]);

        $response->assertCreated()
            ->assertJsonPath('data.assigned_to', $officer->id);

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'under_investigation']);
        $this->assertDatabaseHas('loan_investigations', ['loan_application_id' => $application->id, 'assigned_to' => $officer->id, 'status' => 'assigned']);
    }

    public function test_investigation_requires_guarantors_confirmed_first(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($member, $product));
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/assign', ['assigned_to' => $officer->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');
    }

    public function test_committee_officer_cannot_assign_investigations(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/assign', ['assigned_to' => $officer->id])
            ->assertForbidden();
    }

    public function test_only_the_assigned_investigator_can_update_the_investigation(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $anotherOfficer = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officer);

        $this->actingAs($anotherOfficer, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation', ['member_findings' => 'Sneaky edit.'])
            ->assertForbidden();

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation', ['member_findings' => 'All clean.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_investigation_submit_without_a_recommendation_is_rejected(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officer);

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation/submit', ['member_findings' => 'Checked.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recommendation');
    }

    public function test_submitted_investigation_advances_application_to_pending_admin_decision(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $investigation = $this->submittedInvestigation($application, $officer);

        $this->assertSame('submitted', $investigation->status);
        $this->assertNotNull($investigation->submitted_at);
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'pending_admin_decision']);
    }

    public function test_approval_requires_a_submitted_investigation(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officer); // assigned but never submitted

        // Force the application into the decision stage to reach the service
        // invariant directly; a submitted investigation is still required (D-8).
        $application->update(['status' => 'pending_admin_decision']);
        $application->refresh();

        $this->expectException(ValidationException::class);
        app(LoanDecisionService::class)->approve($application, $this->userWithRole('admin'), $this->approvalTerms());
    }

    public function test_approval_requires_explicit_final_terms(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));

        $terms = $this->approvalTerms();
        unset($terms['interest_rate_basis_points']);

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/approve', $terms)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('interest_rate_basis_points');
    }

    public function test_approval_creates_a_pending_disbursement_loan_and_snapshots_terms(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));
        $admin = $this->userWithRole('admin');

        $terms = [
            'approved_amount_minor' => 400000,
            'interest_rate_basis_points' => 1050,
            'interest_method' => 'reducing_balance',
            'repayment_months' => 8,
            'decision_reason' => 'Approve at revised terms.',
        ];

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loans/applications/'.$application->id.'/approve', $terms)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_disbursement')
            ->assertJsonPath('data.principal_amount_minor', 400000);

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->assertDatabaseHas('loan_decisions', [
            'loan_application_id' => $application->id,
            'decision' => 'approved',
            'decided_by' => $admin->id,
            'approved_amount_minor' => 400000,
            'interest_rate_basis_points' => 1050,
            'interest_method' => 'reducing_balance',
            'repayment_months' => 8,
            'decision_reason' => 'Approve at revised terms.',
        ]);
        $this->assertDatabaseHas('loans', [
            'loan_application_id' => $application->id,
            'status' => 'pending_disbursement',
            'principal_amount_minor' => 400000,
            'interest_rate_basis_points' => 1050,
            'interest_method' => 'reducing_balance',
            'repayment_months' => 8,
        ]);
    }

    public function test_approval_without_decision_reason_is_rejected(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));
        $admin = $this->userWithRole('admin');

        $terms = $this->approvalTerms();
        unset($terms['decision_reason']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loans/applications/'.$application->id.'/approve', $terms)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision_reason');

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'pending_admin_decision']);
        $this->assertDatabaseMissing('loan_decisions', ['loan_application_id' => $application->id]);
    }

    public function test_approval_rejects_a_blank_decision_reason(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));
        $admin = $this->userWithRole('admin');

        // A whitespace-only reason defeats the request-level required rule, so
        // the service enforces a non-empty reason as an invariant (RA-6).
        $this->expectException(ValidationException::class);

        app(LoanDecisionService::class)->approve($application, $admin, $this->approvalTerms(['decision_reason' => '   ']));
    }

    public function test_product_edits_after_approval_do_not_alter_snapshots(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));

        $this->approveApplication($application, $this->userWithRole('admin'), [
            'interest_rate_basis_points' => 900,
            'interest_method' => 'flat',
            'repayment_months' => 6,
        ]);

        app(LoanProductService::class)->update($product, [
            'interest_rate_basis_points' => 9999,
            'interest_method' => 'reducing_balance',
            'repayment_months' => 24,
        ]);

        $decision = $application->decision->fresh();
        $loan = $application->loan->fresh();

        $this->assertSame(900, $decision->interest_rate_basis_points);
        $this->assertSame('flat', $decision->interest_method);
        $this->assertSame(6, $decision->repayment_months);
        $this->assertSame(900, $loan->interest_rate_basis_points);
        $this->assertSame('flat', $loan->interest_method);
        $this->assertSame(6, $loan->repayment_months);
    }

    public function test_approval_is_blocked_while_the_member_is_suspended(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));

        $member->update(['status' => 'suspended']);

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/approve', $this->approvalTerms())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'pending_admin_decision']);
    }

    public function test_committee_officer_cannot_approve_an_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();
    }

    public function test_admin_cannot_decide_their_own_application(): void
    {
        $applicant = $this->loanMember();
        $applicant->user->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($applicant, $product, $this->userWithRole('committee_officer'));

        $this->actingAs($applicant->user, 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();

        $this->actingAs($applicant->user, 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/reject', ['reason' => 'Self rejection.'])
            ->assertForbidden();
    }

    public function test_approval_is_blocked_unless_the_application_is_pending_admin_decision(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/approve', $this->approvalTerms())
            ->assertForbidden();
    }

    public function test_rejection_is_allowed_without_an_investigation(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->confirmedApplication($member, $product);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/reject', ['reason' => 'Insufficient repayment capacity.'])
            ->assertOk();

        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'rejected', 'rejection_reason' => 'Insufficient repayment capacity.']);
        $this->assertDatabaseHas('loan_decisions', ['loan_application_id' => $application->id, 'decision' => 'rejected', 'decided_by' => $admin->id]);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/reject', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_rejected_application_is_terminal_and_a_new_application_can_be_opened(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/reject', ['reason' => 'Policy mismatch.'])
            ->assertOk();

        // The rejected application is preserved (no hard delete)...
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'rejected']);

        // ...and the member can open a new application.
        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications', [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => 500000,
            'purpose' => 'Fresh application after rejection.',
        ])->assertCreated();
    }

    public function test_complete_approval_workflow_writes_no_financial_transactions(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));

        $this->approveApplication($application, $this->userWithRole('admin'));

        $this->assertDatabaseCount('financial_transactions', 0);
    }
}
