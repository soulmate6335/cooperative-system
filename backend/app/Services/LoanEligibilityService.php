<?php

namespace App\Services;

use App\Contracts\LoanLimitRule;
use App\Models\FinancialAccount;
use App\Models\LoanProduct;
use App\Models\Member;
use Illuminate\Contracts\Container\Container;
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
}
