<?php

namespace App\Services;

use App\Models\CommitteeMeeting;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanApplicationService
{
    public function __construct(private readonly LoanEligibilityService $eligibility) {}

    public function create(Member $member, array $data): LoanApplication
    {
        return DB::transaction(function () use ($member, $data): LoanApplication {
            $member = Member::query()->lockForUpdate()->find($member->id);

            if ($member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'An active membership is required to apply for a loan.']);
            }

            // RA-3: only one open application per member. The partial unique
            // index backs this up at the database level for concurrent creates.
            if ($member->loanApplications()->whereNotIn('status', LoanApplication::TERMINAL_STATUSES)->exists()) {
                throw ValidationException::withMessages(['application' => 'A member may only have one open loan application.']);
            }

            $product = LoanProduct::query()->find($data['loan_product_id']);

            if (! $product instanceof LoanProduct || ! $product->isActive()) {
                throw ValidationException::withMessages(['loan_product_id' => 'The loan product must be active.']);
            }

            $this->assertAmountWithinProduct($product, (int) $data['amount_requested_minor']);

            return LoanApplication::create([
                'application_number' => $this->uniqueApplicationNumber(),
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'amount_requested_minor' => (int) $data['amount_requested_minor'],
                'purpose' => $data['purpose'],
                'status' => 'draft',
            ]);
        });
    }

    /**
     * A member may edit only their own draft. Status, meeting binding,
     * guarantor state, decisions and member ownership are workflow-owned
     * and cannot be changed through draft editing.
     */
    public function updateDraft(LoanApplication $application, array $data): LoanApplication
    {
        return DB::transaction(function () use ($application, $data): LoanApplication {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);

            if ($application->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'Member-driven actions are frozen until the membership is reactivated.']);
            }

            if ($application->status !== 'draft') {
                throw ValidationException::withMessages(['application' => 'Only draft applications can be edited.']);
            }

            // Bounds are validated against the product the draft will end up
            // on, i.e. the new product when it is switched, otherwise the
            // currently selected one.
            $product = $application->product;
            $updates = [];

            if (array_key_exists('loan_product_id', $data)) {
                $product = LoanProduct::query()->find($data['loan_product_id']);

                if (! $product instanceof LoanProduct || ! $product->isActive()) {
                    throw ValidationException::withMessages(['loan_product_id' => 'The loan product must be active.']);
                }

                $updates['loan_product_id'] = $product->id;
            }

            if (array_key_exists('amount_requested_minor', $data)) {
                $amount = (int) $data['amount_requested_minor'];

                $this->assertAmountWithinProduct($product, $amount);

                $updates['amount_requested_minor'] = $amount;
            }

            if (array_key_exists('purpose', $data)) {
                $updates['purpose'] = $data['purpose'];
            }

            if ($updates !== []) {
                $application->update($updates);
            }

            return $application->fresh();
        });
    }

    public function submit(LoanApplication $application): LoanApplication
    {
        return DB::transaction(function () use ($application): LoanApplication {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);
            $member = $application->member;

            if ($member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'An active membership is required to submit a loan application.']);
            }

            if ($application->status !== 'draft') {
                throw ValidationException::withMessages(['application' => 'Only draft applications can be submitted.']);
            }

            // RA-4: submission must revalidate that the product is still active.
            $product = $application->product;
            if (! $product->isActive()) {
                throw ValidationException::withMessages(['loan_product_id' => 'The selected loan product is no longer active.']);
            }

            $this->assertAdminEligibilityForSubmission($member, $product);
            $this->assertAmountWithinProduct($product, $application->amount_requested_minor);

            $submittedAt = now();
            $meeting = $this->findEligibleMeeting($submittedAt);

            $application->update([
                'committee_meeting_id' => $meeting->id,
                'status' => $application->guarantors()->exists() ? 'awaiting_guarantors' : 'submitted',
                'submitted_at' => $submittedAt,
                'savings_balance_minor' => $this->eligibility->savingsBalance($member),
                'shares_balance_minor' => $this->eligibility->sharesBalance($member),
            ]);

            return $application->fresh();
        });
    }

    public function cancelByMember(LoanApplication $application, Member $member): LoanApplication
    {
        return DB::transaction(function () use ($application, $member): LoanApplication {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);

            if ($application->member_id !== $member->id) {
                throw ValidationException::withMessages(['application' => 'You can only cancel your own application.']);
            }

            if ($application->isTerminal()) {
                throw ValidationException::withMessages(['application' => 'Only open applications can be cancelled.']);
            }

            if (! in_array($application->status, ['draft', 'submitted', 'awaiting_guarantors', 'guarantors_confirmed'], true)) {
                throw ValidationException::withMessages(['application' => 'This application can no longer be cancelled by the member.']);
            }

            // RA-5: a suspended member cannot drive cancellation; the admin
            // cancellation path exists for that resolution.
            if ($member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'Member-driven actions are frozen until the membership is reactivated.']);
            }

            $application->update(['status' => 'cancelled']);

            return $application->fresh();
        });
    }

    public function cancelByAdmin(LoanApplication $application, ?string $reason = null): LoanApplication
    {
        return DB::transaction(function () use ($application, $reason): LoanApplication {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);

            if ($application->isTerminal()) {
                throw ValidationException::withMessages(['application' => 'Only open applications can be cancelled.']);
            }

            $application->update(['status' => 'cancelled', 'rejection_reason' => $reason]);

            return $application->fresh();
        });
    }

    /**
     * The authoritative eligibility gate (RA-ELIGIBILITY): a member may only
     * submit an application once an administrator has explicitly granted
     * eligibility for this product. The calculated system factors are
     * informational only; an administrative approval (including an explicit
     * override) is what permits entry into the application workflow.
     */
    private function assertAdminEligibilityForSubmission(Member $member, LoanProduct $product): void
    {
        $decision = $this->eligibility->currentDecisionStatusFor($member, $product);

        if ($decision !== 'eligible') {
            throw ValidationException::withMessages([
                'eligibility' => $decision === 'pending'
                    ? 'Your eligibility is awaiting administrative review. Please contact the cooperative office.'
                    : 'Your eligibility has not been approved by an administrator.',
            ]);
        }
    }

    private function assertAmountWithinProduct(LoanProduct $product, int $amount): void
    {
        if ($amount < $product->minimum_amount_minor) {
            throw ValidationException::withMessages(['amount_requested_minor' => 'The requested amount is below the product minimum.']);
        }

        if ($product->maximum_amount_minor !== null && $amount > $product->maximum_amount_minor) {
            throw ValidationException::withMessages(['amount_requested_minor' => 'The requested amount exceeds the product maximum.']);
        }
    }

    /**
     * RA-9: bind to the earliest future scheduled meeting whose date satisfies
     * the configured cutoff relative to the submission time.
     */
    private function findEligibleMeeting(Carbon $submittedAt): CommitteeMeeting
    {
        $meetings = CommitteeMeeting::query()
            ->where('status', 'scheduled')
            ->where('meeting_date', '>=', $submittedAt)
            ->orderBy('meeting_date')
            ->get();

        $meeting = $meetings->first(
            fn (CommitteeMeeting $meeting): bool => $meeting->meeting_date->gte($submittedAt->copy()->addDays($meeting->cutoff_days))
        );

        if ($meeting === null) {
            throw ValidationException::withMessages(['committee_meeting' => 'No scheduled committee meeting satisfies the required submission cutoff. Please contact an administrator.']);
        }

        return $meeting;
    }

    private function uniqueApplicationNumber(): string
    {
        do {
            $number = 'LAPP-'.strtoupper(Str::random(10));
        } while (LoanApplication::where('application_number', $number)->exists());

        return $number;
    }
}
