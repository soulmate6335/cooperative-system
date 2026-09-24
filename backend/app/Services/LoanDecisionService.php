<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDecision;
use App\Models\LoanEligibilityDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanDecisionService
{
    public function __construct(private readonly LoanEligibilityService $eligibility) {}

    public function approve(LoanApplication $application, User $admin, array $terms): Loan
    {
        return DB::transaction(function () use ($application, $admin, $terms): Loan {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);

            if ($application->member->user_id === $admin->id) {
                throw ValidationException::withMessages(['application' => 'An administrator cannot decide their own loan application.']);
            }

            if ($application->status !== 'pending_admin_decision') {
                throw ValidationException::withMessages(['application' => 'Only applications pending admin decision can be approved.']);
            }

            if ($application->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'The application is frozen while the member is suspended.']);
            }

            // RA-ELIGIBILITY: approval revalidates the current eligibility
            // authority. Eligibility history is append-only, so a decision
            // recorded after submission may have changed the member's
            // standing; a stale eligible decision cannot justify approval.
            if ($this->eligibility->currentDecisionStatusFor($application->member, $application->product) !== LoanEligibilityDecision::ELIGIBLE) {
                throw ValidationException::withMessages(['eligibility' => 'The member must hold a current eligible loan eligibility decision.']);
            }

            // D-8: a submitted committee investigation is required for approval.
            $investigation = $application->investigation;
            if (! $investigation?->isSubmitted()) {
                throw ValidationException::withMessages(['application' => 'A submitted committee investigation is required before approval.']);
            }

            if ($application->acceptedGuarantorCount() < $application->product->required_guarantors) {
                throw ValidationException::withMessages(['guarantors' => 'The required number of guarantors has not accepted.']);
            }

            $approvedAmount = (int) $terms['approved_amount_minor'];
            $product = $application->product;

            if ($approvedAmount < $product->minimum_amount_minor || ($product->maximum_amount_minor !== null && $approvedAmount > $product->maximum_amount_minor)) {
                throw ValidationException::withMessages(['approved_amount_minor' => 'The approved amount is outside the product range.']);
            }

            // RA-6: final terms must be explicitly resolved at approval and are
            // snapshotted so later product edits never alter them. The service
            // revalidates the resolved values rather than trusting the request layer.
            foreach (['interest_rate_basis_points', 'interest_method', 'repayment_months'] as $field) {
                if (! isset($terms[$field]) || $terms[$field] === null || $terms[$field] === '') {
                    throw ValidationException::withMessages([$field => 'The final term must be explicitly resolved at approval.']);
                }
            }

            $interestRate = (int) $terms['interest_rate_basis_points'];
            $interestMethod = $terms['interest_method'];
            $repaymentMonths = (int) $terms['repayment_months'];

            if ($interestRate < 0) {
                throw ValidationException::withMessages(['interest_rate_basis_points' => 'The interest rate cannot be negative.']);
            }

            if (! in_array($interestMethod, ['flat', 'reducing_balance'], true)) {
                throw ValidationException::withMessages(['interest_method' => 'The interest method is not supported.']);
            }

            if ($repaymentMonths < 1) {
                throw ValidationException::withMessages(['repayment_months' => 'Repayment months must be at least 1.']);
            }

            // RA-6: an explicit, non-empty decision reason is required to approve.
            if (! isset($terms['decision_reason']) || trim((string) $terms['decision_reason']) === '') {
                throw ValidationException::withMessages(['decision_reason' => 'A non-empty decision reason is required to approve the loan.']);
            }

            LoanDecision::create([
                'loan_application_id' => $application->id,
                'decided_by' => $admin->id,
                'decision' => 'approved',
                'approved_amount_minor' => $approvedAmount,
                'interest_rate_basis_points' => $interestRate,
                'interest_method' => $interestMethod,
                'repayment_months' => $repaymentMonths,
                'decision_reason' => $terms['decision_reason'],
                'decided_at' => now(),
            ]);

            $loan = Loan::create([
                'loan_application_id' => $application->id,
                'member_id' => $application->member_id,
                'loan_number' => $this->uniqueLoanNumber(),
                'principal_amount_minor' => $approvedAmount,
                'interest_rate_basis_points' => $interestRate,
                'interest_method' => $interestMethod,
                'repayment_months' => $repaymentMonths,
                'status' => 'pending_disbursement',
            ]);

            $application->update(['status' => 'approved']);

            return $loan;
        });
    }

    public function reject(LoanApplication $application, User $admin, string $reason): LoanApplication
    {
        return DB::transaction(function () use ($application, $admin, $reason): LoanApplication {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);

            if ($application->member->user_id === $admin->id) {
                throw ValidationException::withMessages(['application' => 'An administrator cannot decide their own loan application.']);
            }

            // RA-8: rejection is allowed from any post-submission state and does
            // not require a committee investigation.
            if (! in_array($application->status, ['submitted', 'awaiting_guarantors', 'guarantors_confirmed', 'under_investigation', 'committee_reviewed', 'pending_admin_decision'], true)) {
                throw ValidationException::withMessages(['application' => 'Only submitted applications can be rejected.']);
            }

            LoanDecision::create([
                'loan_application_id' => $application->id,
                'decided_by' => $admin->id,
                'decision' => 'rejected',
                'decision_reason' => $reason,
                'decided_at' => now(),
            ]);

            $application->update(['status' => 'rejected', 'rejection_reason' => $reason]);

            return $application->fresh();
        });
    }

    private function uniqueLoanNumber(): string
    {
        do {
            $number = 'LN-'.strtoupper(Str::random(10));
        } while (Loan::where('loan_number', $number)->exists());

        return $number;
    }
}
