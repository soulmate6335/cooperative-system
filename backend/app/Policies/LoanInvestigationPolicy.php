<?php

namespace App\Policies;

use App\Models\User;

class LoanInvestigationPolicy
{
    public function assign(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('loans.investigate');
    }
}
