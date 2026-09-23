<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\LoanGuarantorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class LoanGuarantorTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Submit an application and create one pending guarantor request.
     *
     * @return array{0: LoanApplication, 1: LoanGuarantor}
     */
    private function submittedWithGuarantorRequest(Member $applicant, LoanProduct $product, Member $guarantor): array
    {
        $application = $this->submitApplication($this->draftApplication($applicant, $product));
        $request = app(LoanGuarantorService::class)->request($application, $applicant, $guarantor->id);

        return [$application->fresh(), $request];
    }

    public function test_a_member_cannot_guarantee_their_own_loan(): void
    {
        $member = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($member, $this->loanProduct()));

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/guarantors', [
            'guarantor_member_id' => $member->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor_member_id');
    }

    public function test_an_inactive_member_cannot_be_requested_as_a_guarantor(): void
    {
        $member = $this->loanMember();
        $inactive = $this->loanMember('suspended');
        $application = $this->submitApplication($this->draftApplication($member, $this->loanProduct()));

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/guarantors', [
            'guarantor_member_id' => $inactive->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor_member_id');
    }

    public function test_a_duplicate_guarantor_request_is_rejected(): void
    {
        $member = $this->loanMember();
        $guarantor = $this->loanMember();
        $this->submittedWithGuarantorRequest($member, $this->loanProduct(), $guarantor);

        $application = $member->loanApplications()->latest('id')->firstOrFail();

        $this->actingAs($member->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/guarantors', [
            'guarantor_member_id' => $guarantor->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor_member_id');

        $this->assertDatabaseCount('loan_guarantors', 1);
    }

    public function test_a_member_with_an_active_guarantee_obligation_cannot_be_requested_again(): void
    {
        $applicantA = $this->loanMember();
        $applicantB = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);

        $applicationA = $this->submitApplication($this->draftApplication($applicantA, $product));
        $requestA = app(LoanGuarantorService::class)->request($applicationA, $applicantA, $guarantor->id);
        $this->acceptGuarantor($requestA);
        $this->assertSame('guarantors_confirmed', $applicationA->fresh()->status);

        $applicationB = $this->submitApplication($this->draftApplication($applicantB, $product));

        $this->actingAs($applicantB->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$applicationB->id.'/guarantors', [
            'guarantor_member_id' => $guarantor->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor_member_id');
    }

    public function test_a_guarantor_who_is_suspended_at_acceptance_time_is_rejected(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $guarantor->update(['status' => 'suspended']);

        $this->actingAs($guarantor->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/accept')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor');

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertNull($request->responded_at);
    }

    public function test_a_member_cannot_respond_to_someone_elses_guarantee_request(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $other = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $this->actingAs($other->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/accept')
            ->assertForbidden();
    }

    public function test_application_reaches_guarantors_confirmed_when_the_required_count_accepts(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $this->assertSame('awaiting_guarantors', $request->application->fresh()->status);

        $this->acceptGuarantor($request);

        $this->assertSame('guarantors_confirmed', $request->application->fresh()->status);
    }

    public function test_accepted_guarantor_decline_after_confirmation_reverts_and_allows_replacement(): void
    {
        $applicant = $this->loanMember();
        $g1 = $this->loanMember();
        $g2 = $this->loanMember();
        $g3 = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        $request1 = app(LoanGuarantorService::class)->request($application, $applicant, $g1->id);
        $request2 = app(LoanGuarantorService::class)->request($application, $applicant, $g2->id);
        $this->acceptGuarantor($request1);
        $this->acceptGuarantor($request2);

        $this->assertSame('guarantors_confirmed', $application->fresh()->status);

        // RA-2: an accepted guarantor backs out after confirmation.
        app(LoanGuarantorService::class)->decline($request2, $g2->user);

        $this->assertSame('awaiting_guarantors', $application->fresh()->status);

        // A replacement guarantor can be requested and reconfirm the stage.
        $request3 = app(LoanGuarantorService::class)->request($application, $applicant, $g3->id);
        $this->acceptGuarantor($request3);

        $this->assertSame('guarantors_confirmed', $application->fresh()->status);
        $this->assertDatabaseHas('loan_guarantors', ['id' => $request2->id, 'status' => 'declined']);
        $this->assertNotNull($request2->fresh()->responded_at);
    }

    public function test_decline_before_confirmation_keeps_application_in_awaiting_guarantors(): void
    {
        $applicant = $this->loanMember();
        $g1 = $this->loanMember();
        $g2 = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        $request1 = app(LoanGuarantorService::class)->request($application, $applicant, $g1->id);
        $this->acceptGuarantor($request1);

        $request2 = app(LoanGuarantorService::class)->request($application, $applicant, $g2->id);
        app(LoanGuarantorService::class)->decline($request2, $g2->user);

        $this->assertSame('awaiting_guarantors', $application->fresh()->status);
    }

    public function test_guarantor_requests_are_rejected_after_confirmation(): void
    {
        $applicant = $this->loanMember();
        $g1 = $this->loanMember();
        $g2 = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [$application, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $g1);
        $this->acceptGuarantor($request);
        $this->assertSame('guarantors_confirmed', $application->fresh()->status);

        $this->actingAs($applicant->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/guarantors', [
            'guarantor_member_id' => $g2->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('application');
    }

    public function test_cancelling_all_pending_requests_returns_application_to_submitted(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));
        $request = app(LoanGuarantorService::class)->request($application, $applicant, $guarantor->id);

        $this->assertSame('awaiting_guarantors', $application->fresh()->status);

        $this->actingAs($applicant->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/guarantors/'.$request->id.'/cancel')
            ->assertOk();

        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertDatabaseHas('loan_guarantors', ['id' => $request->id, 'status' => 'cancelled']);
    }

    public function test_a_member_cannot_request_guarantors_on_someone_elses_application(): void
    {
        $applicant = $this->loanMember();
        $other = $this->loanMember();
        $guarantor = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($applicant, $this->loanProduct()));

        $this->actingAs($other->user, 'sanctum')->postJson('/api/v1/member/loans/applications/'.$application->id.'/guarantors', [
            'guarantor_member_id' => $guarantor->id,
        ])->assertForbidden();
    }

    public function test_suspended_applicant_cannot_progress_through_guarantor_acceptance(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $this->assertSame('awaiting_guarantors', $request->application->fresh()->status);

        // D-7: the applicant is suspended after the request was made.
        $applicant->update(['status' => 'suspended']);

        $this->actingAs($guarantor->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/accept')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');

        // The request and the application workflow are unchanged.
        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertNull($request->responded_at);
        $this->assertSame('awaiting_guarantors', $request->application->fresh()->status);
    }

    public function test_an_active_guarantor_cannot_accept_when_the_applicant_is_suspended(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        // RA-10 is satisfied: the guarantor is still active. Only the applicant's
        // membership blocks the acceptance (D-7).
        $this->assertSame('active', $guarantor->fresh()->status);
        $applicant->update(['status' => 'suspended']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Member-driven actions are frozen until the membership is reactivated.');

        app(LoanGuarantorService::class)->accept($request, $guarantor->user);
    }

    public function test_guarantor_decline_does_not_change_state_when_the_applicant_is_suspended(): void
    {
        $applicant = $this->loanMember();
        $g1 = $this->loanMember();
        $g2 = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        $request1 = app(LoanGuarantorService::class)->request($application, $applicant, $g1->id);
        $request2 = app(LoanGuarantorService::class)->request($application, $applicant, $g2->id);
        $this->acceptGuarantor($request1);
        $this->acceptGuarantor($request2);

        $this->assertSame('guarantors_confirmed', $application->fresh()->status);

        // D-7: the applicant is suspended after confirmation. A decline must not
        // revert the confirmed stage or mark the accepted request declined.
        $applicant->update(['status' => 'suspended']);

        $this->actingAs($g2->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request2->id.'/decline')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');

        $this->assertSame('accepted', $request2->fresh()->status);
        $this->assertSame('guarantors_confirmed', $application->fresh()->status);
    }

    public function test_guarantor_candidates_list_only_active_eligible_members(): void
    {
        $applicant = $this->loanMember();
        $candidate = $this->loanMember();
        $suspended = $this->loanMember('suspended');
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        $this->actingAs($applicant->user, 'sanctum')->getJson('/api/v1/member/loans/applications/'.$application->id.'/guarantor-candidates')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $candidate->id)
            ->assertJsonPath('data.0.name', $candidate->user->name);
    }

    public function test_guarantor_candidates_exclude_already_requested_and_exposed_members(): void
    {
        $applicant = $this->loanMember();
        $requested = $this->loanMember();
        $exposed = $this->loanMember();
        $available = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 3]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        app(LoanGuarantorService::class)->request($application, $applicant, $requested->id);

        // $exposed carries an active obligation from another member's loan.
        $otherApplicant = $this->loanMember();
        $otherProduct = $this->loanProduct(['required_guarantors' => 1]);
        $otherApplication = $this->submitApplication($this->draftApplication($otherApplicant, $otherProduct));
        $otherRequest = app(LoanGuarantorService::class)->request($otherApplication, $otherApplicant, $exposed->id);
        $this->acceptGuarantor($otherRequest);

        $response = $this->actingAs($applicant->user, 'sanctum')->getJson('/api/v1/member/loans/applications/'.$application->id.'/guarantor-candidates')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($available->id, $ids);
        $this->assertContains($otherApplicant->id, $ids);
        $this->assertNotContains($requested->id, $ids);
        $this->assertNotContains($exposed->id, $ids);
        $this->assertNotContains($applicant->id, $ids);
    }

    public function test_guarantor_candidates_support_searching_by_member_name(): void
    {
        $applicant = $this->loanMember();
        $alice = $this->loanMember();
        $bob = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        $this->actingAs($applicant->user, 'sanctum')->getJson('/api/v1/member/loans/applications/'.$application->id.'/guarantor-candidates?search='.urlencode($alice->user->name))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $alice->id);
    }

    public function test_a_non_owner_cannot_list_guarantor_candidates(): void
    {
        $applicant = $this->loanMember();
        $other = $this->loanMember();
        $product = $this->loanProduct();
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        $this->actingAs($other->user, 'sanctum')->getJson('/api/v1/member/loans/applications/'.$application->id.'/guarantor-candidates')
            ->assertForbidden();
    }

    public function test_guarantor_request_list_returns_application_details(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $this->actingAs($guarantor->user, 'sanctum')->getJson('/api/v1/member/guarantor-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $request->id)
            ->assertJsonPath('data.0.application.application_number', $request->application->application_number)
            ->assertJsonPath('data.0.application.amount_requested_minor', $request->application->amount_requested_minor)
            ->assertJsonPath('data.0.application.loan_product.id', $product->id)
            ->assertJsonPath('data.0.application.member.member_number', $applicant->member_number)
            ->assertJsonPath('data.0.application.member.name', $applicant->user->name);
    }

    public function test_an_already_accepted_request_cannot_be_accepted_again(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $this->acceptGuarantor($request);
        $this->assertSame('accepted', $request->fresh()->status);

        $this->actingAs($guarantor->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/accept')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor');

        $this->assertSame('accepted', $request->fresh()->status);
    }

    public function test_a_declined_request_cannot_be_changed_back_to_accepted(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $this->actingAs($guarantor->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/decline')
            ->assertOk();

        $this->assertSame('declined', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->responded_at);

        $this->actingAs($guarantor->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/accept')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor');

        $this->assertSame('declined', $request->fresh()->status);
    }

    public function test_partial_acceptance_does_not_advance_the_application(): void
    {
        $applicant = $this->loanMember();
        $g1 = $this->loanMember();
        $g2 = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 2]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        $request1 = app(LoanGuarantorService::class)->request($application, $applicant, $g1->id);
        $this->acceptGuarantor($request1);

        $this->assertSame('awaiting_guarantors', $application->fresh()->status);
    }

    public function test_the_applicant_cannot_accept_a_guarantee_request_on_their_own_application(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        [, $request] = $this->submittedWithGuarantorRequest($applicant, $product, $guarantor);

        $this->actingAs($applicant->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$request->id.'/accept')
            ->assertForbidden();
    }

    public function test_guarantee_exposure_ends_when_the_application_reaches_a_terminal_state(): void
    {
        $applicantA = $this->loanMember();
        $applicantB = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $admin = $this->userWithRole('admin');

        $applicationA = $this->submitApplication($this->draftApplication($applicantA, $product));
        $requestA = app(LoanGuarantorService::class)->request($applicationA, $applicantA, $guarantor->id);
        $this->acceptGuarantor($requestA);
        $this->assertSame('guarantors_confirmed', $applicationA->fresh()->status);

        // The admin cancels application A; the guarantor's exposure is released.
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loans/applications/'.$applicationA->id.'/cancel', ['reason' => 'Administrative cancellation'])
            ->assertOk();
        $this->assertSame('cancelled', $applicationA->fresh()->status);

        $applicationB = $this->submitApplication($this->draftApplication($applicantB, $product));
        $requestB = app(LoanGuarantorService::class)->request($applicationB, $applicantB, $guarantor->id);
        $this->acceptGuarantor($requestB);

        $this->assertSame('accepted', $requestB->fresh()->status);
        $this->assertSame('guarantors_confirmed', $applicationB->fresh()->status);
    }

    public function test_exposure_is_revalidated_at_the_time_of_acceptance(): void
    {
        $applicantB = $this->loanMember();
        $applicantA = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);

        $applicationB = $this->submitApplication($this->draftApplication($applicantB, $product));
        $requestB = app(LoanGuarantorService::class)->request($applicationB, $applicantB, $guarantor->id);

        // Between the request and the response, the guarantor accepts another
        // obligation, so the pending request must now fail the exposure rule.
        $applicationA = $this->submitApplication($this->draftApplication($applicantA, $product));
        $requestA = app(LoanGuarantorService::class)->request($applicationA, $applicantA, $guarantor->id);
        $this->acceptGuarantor($requestA);

        $this->actingAs($guarantor->user, 'sanctum')->postJson('/api/v1/member/guarantor-requests/'.$requestB->id.'/accept')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guarantor');

        $this->assertSame('pending', $requestB->fresh()->status);
    }
}
