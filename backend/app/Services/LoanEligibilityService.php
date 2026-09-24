<?php

namespace App\Services;

use App\Contracts\LoanLimitRule;
use App\Models\FinancialAccount;
use App\Models\LoanEligibilityDecision;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanEligibilityService
{
    public function __construct(
        private readonly FinancialCoreService $financialCore,
        private readonly Container $container,
    ) {}

    public function membershipMonths(Member $member): int
    {
        return $member->joined_at->diffInMonths(now());
    }

    public function savingsBalance(Member $member): int
    {
        $account = $member->financialAccounts()->where('account_type', 'savings')->first();

        return $account instanceof FinancialAccount ? $this->financialCore->balance($account) : 0;
    }

    public function sharesBalance(Member $member): int
    {
        $account = $member->financialAccounts()->where('account_type', 'shares')->first();

        return $account instanceof FinancialAccount ? $this->financialCore->balance($account) : 0;
    }

    /**
     * Informational eligibility factors. Balances are read only through
     * FinancialCoreService::balance() and are not authoritative.
     *
     * @return array<string, mixed>
     */
    public function factorsFor(Member $member, LoanProduct $product): array
    {
        $reasons = [];

        if ($member->status !== 'active') {
            $reasons[] = 'An active membership is required.';
        }

        if (! $product->isActive()) {
            $reasons[] = 'The loan product is inactive.';
        }

        if ($this->membershipMonths($member) < $product->minimum_membership_months) {
            $reasons[] = 'The minimum membership period has not been completed.';
        }

        return [
            'eligible' => count($reasons) === 0,
            'membership_months' => $this->membershipMonths($member),
            'minimum_membership_months' => $product->minimum_membership_months,
            'member_active' => $member->status === 'active',
            'product_active' => $product->isActive(),
            'savings_balance_minor' => $this->savingsBalance($member),
            'shares_balance_minor' => $this->sharesBalance($member),
            'minimum_amount_minor' => $product->minimum_amount_minor,
            'maximum_amount_minor' => $product->maximum_amount_minor,
            'computed_maximum_amount_minor' => $this->limitRule()?->computedMaximumMinor($member, $product),
            'reasons' => $reasons,
        ];
    }

    /**
     * The registered LoanLimitRule strategy, if one is bound in the container
     * (D-1). No concrete rule ships yet, so unless a rule is bound the
     * computed maximum remains null.
     */
    private function limitRule(): ?LoanLimitRule
    {
        if (! $this->container->bound(LoanLimitRule::class)) {
            return null;
        }

        return $this->container->make(LoanLimitRule::class);
    }

    public function assertEligibleForSubmission(Member $member, LoanProduct $product): void
    {
        $factors = $this->factorsFor($member, $product);

        if (! $factors['eligible']) {
            throw ValidationException::withMessages(['eligibility' => implode(' ', $factors['reasons'])]);
        }
    }

    // ------------------------------------------------ administrative decisions

    /**
     * The most recent administrative decision for a member + product pair,
     * ordered by the time it was recorded. Null means the pair is awaiting
     * administrative review (pending).
     */
    public function latestDecisionFor(Member $member, LoanProduct $product): ?LoanEligibilityDecision
    {
        return LoanEligibilityDecision::query()
            ->with('decidedBy')
            ->where('member_id', $member->id)
            ->where('loan_product_id', $product->id)
            ->orderByDesc('decided_at')
            ->orderByDesc('created_at')
            // UUIDv7 keys are time-ordered, so this tiebreaker makes "latest"
            // deterministic even when two decisions share the same second.
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Authoritative eligibility status for a member + product pair:
     * 'eligible', 'ineligible' or 'pending' when no decision has been made.
     */
    public function currentDecisionStatusFor(Member $member, LoanProduct $product): string
    {
        return $this->latestDecisionFor($member, $product)?->status ?? 'pending';
    }

    /**
     * The member-safe decision summary exposed to the member and admin APIs.
     *
     * @return array<string, mixed>
     */
    public function decisionSummaryFor(Member $member, LoanProduct $product): array
    {
        $decision = $this->latestDecisionFor($member, $product);

        return [
            'status' => $decision?->status ?? 'pending',
            'decided_by' => $decision?->decidedBy !== null
                ? ['id' => $decision->decidedBy->id, 'name' => $decision->decidedBy->name]
                : null,
            'reason' => $decision?->reason ?? null,
            'decided_at' => $decision?->decided_at?->toISOString(),
        ];
    }

    /**
     * Record a new administrative eligibility decision. Decisions are
     * append-only history: each review inserts a fresh row and never
     * mutates the underlying member or financial data.
     */
    public function recordDecision(
        Member $member,
        LoanProduct $product,
        User $admin,
        string $status,
        ?string $reason = null,
    ): LoanEligibilityDecision {
        return DB::transaction(function () use ($member, $product, $admin, $status, $reason): LoanEligibilityDecision {
            if ($member->user_id === $admin->id) {
                throw ValidationException::withMessages(['member_id' => 'An administrator cannot decide their own eligibility.']);
            }

            if (! in_array($status, [LoanEligibilityDecision::ELIGIBLE, LoanEligibilityDecision::INELIGIBLE], true)) {
                throw ValidationException::withMessages(['status' => 'The eligibility status is not supported.']);
            }

            $reason = $reason !== null ? trim($reason) : null;
            $reason = $reason === '' ? null : $reason;

            // An approval that contradicts the system assessment is an explicit
            // administrative override and must be justified, never a silent
            // change to the underlying membership, savings or shares data.
            if ($status === LoanEligibilityDecision::ELIGIBLE
                && ! $this->factorsFor($member, $product)['eligible']
                && $reason === null) {
                throw ValidationException::withMessages(['reason' => 'A reason is required when overriding the system eligibility assessment.']);
            }

            return LoanEligibilityDecision::create([
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'status' => $status,
                'decided_by' => $admin->id,
                'reason' => $reason,
                'decided_at' => now(),
            ]);
        });
    }
}
