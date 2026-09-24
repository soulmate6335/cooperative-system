<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\Role;
use App\Services\LoanDecisionService;
use App\Services\LoanGuarantorService;
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

    public function test_committee_queue_lists_applications_ready_for_investigation(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $application->id)
            ->assertJsonPath('data.0.status', 'guarantors_confirmed')
            ->assertJsonPath('data.0.member.name', $member->user->name);
    }

    public function test_committee_queue_lists_investigations_assigned_to_the_officer(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officer);

        $this->assertSame('under_investigation', $application->fresh()->status);

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $application->id);
    }

    public function test_committee_queue_does_not_expose_other_officers_assignments(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officerA = $this->userWithRole('committee_officer');
        $officerB = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officerA);

        $this->actingAs($officerB, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_committee_queue_excludes_applications_without_completed_guarantors(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $guarantor = $this->loanMember();
        $request = app(LoanGuarantorService::class)->request($application, $member, $guarantor->id);
        $this->acceptGuarantor($request);

        $this->assertSame('awaiting_guarantors', $application->fresh()->status);

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_committee_queue_excludes_terminal_applications(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officer);

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/admin/loans/applications/'.$application->id.'/cancel', ['reason' => 'Duplicated request.'])
            ->assertOk();

        $this->assertSame('cancelled', $application->fresh()->status);

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_committee_queue_excludes_confirmed_applications_of_suspended_members(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $member->update(['status' => 'suspended']);

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_committee_queue_requires_an_associated_committee_meeting(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $application->update(['committee_meeting_id' => null]);
        $application->refresh();

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_non_committee_members_cannot_access_the_committee_queue(): void
    {
        $member = $this->loanMember();

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertForbidden();
    }

    public function test_the_applicant_cannot_use_committee_endpoints(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);

        $this->actingAs($member->user, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications/'.$application->id)
            ->assertForbidden();
    }

    public function test_committee_officer_can_start_an_investigation_on_a_ready_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation/start')
            ->assertCreated()
            ->assertJsonPath('data.investigation.assigned_to', $officer->id)
            ->assertJsonPath('data.investigation.status', 'assigned')
            ->assertJsonPath('data.application.status', 'under_investigation');

        $this->assertDatabaseHas('loan_investigations', [
            'loan_application_id' => $application->id,
            'assigned_to' => $officer->id,
        ]);
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'under_investigation']);
    }

    public function test_starting_an_investigation_requires_completed_guarantors(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->submitApplication($this->draftApplication($member, $product));
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation/start')
            ->assertForbidden();
    }

    public function test_starting_an_investigation_outside_review_state_is_rejected(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->submitApplication($this->draftApplication($member, $product));

        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation/start')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');
    }

    public function test_another_officer_cannot_start_an_investigation_already_assigned(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officerA = $this->userWithRole('committee_officer');
        $officerB = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officerA);

        $this->actingAs($officerB, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation/start')
            ->assertForbidden();
    }

    public function test_the_assigned_investigator_can_view_the_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officer);

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.member.name', $member->user->name);
    }

    public function test_an_officer_without_assignment_cannot_view_a_private_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officerA = $this->userWithRole('committee_officer');
        $officerB = $this->userWithRole('committee_officer');
        $this->assignedInvestigation($application, $officerA);

        $this->actingAs($officerB, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications/'.$application->id)
            ->assertForbidden();
    }

    public function test_a_ready_unassigned_application_is_viewable_by_any_committee_officer(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'guarantors_confirmed');
    }

    public function test_committee_officer_cannot_modify_the_application(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->patchJson('/api/v1/member/loans/applications/'.$application->id, ['amount_requested_minor' => 900000])
            ->assertForbidden();
    }

    public function test_committee_officer_cannot_accept_a_guarantee_request(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->acceptedApplication($member, $product, 1);
        $guarantor = $this->loanMember();
        $request = app(LoanGuarantorService::class)->request($application, $member, $guarantor->id);
        $officer = $this->userWithRole('committee_officer');

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/accept')
            ->assertForbidden();
    }

    public function test_a_submitted_investigation_cannot_be_silently_edited(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $this->submittedInvestigation($application, $officer);

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation', ['member_findings' => 'Silent rewrite after submission.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('investigation');
    }

    public function test_duplicate_investigation_submission_is_rejected(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');
        $this->submittedInvestigation($application, $officer);

        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/committee/loan-applications/'.$application->id.'/investigation/submit', ['recommendation' => 'Recommend again.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('investigation');
    }

    public function test_submitting_a_recommendation_creates_no_loan_decisions_or_payments(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $this->submittedInvestigation($application, $officer);

        $this->assertSame('pending_admin_decision', $application->fresh()->status);
        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_decisions', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_committee_queue_hides_investigations_already_submitted(): void
    {
        $member = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->confirmedApplication($member, $product);
        $officer = $this->userWithRole('committee_officer');

        $this->submittedInvestigation($application, $officer);

        $this->assertSame('pending_admin_decision', $application->fresh()->status);

        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/committee/loan-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
