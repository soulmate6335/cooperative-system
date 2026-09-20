<?php

namespace App\Contracts;

use App\Models\LoanProduct;
use App\Models\Member;

/**
 * Strategy extension point for computing a member's maximum loan amount
 * (D-1). No concrete rule is implemented yet, so the computed maximum
 * remains null until a loan formula is defined.
 */
interface LoanLimitRule
{
    /**
     * The maximum loan amount in minor units the member qualifies for on the
     * given product, or null when no limit applies / none is computed.
     */
    public function computedMaximumMinor(Member $member, LoanProduct $product): ?int;
}
