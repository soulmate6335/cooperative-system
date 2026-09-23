<?php

namespace App\Policies;

use App\Models\User;

/**
 * Eligibility decisions are administrative authority: only admin/super_admin
 * users holding the loans.eligibility.manage permission may view assessments
 * or record decisions. Members can never decide their own eligibility.
 */
class LoanEligibilityDecisionPolicy
{
    public function view(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    public function decide(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    private function isAuthorized(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin')
            && $user->hasPermission('loans.eligibility.manage');
    }
}