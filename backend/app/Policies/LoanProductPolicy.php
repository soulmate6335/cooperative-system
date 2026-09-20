<?php

namespace App\Policies;

use App\Models\User;

class LoanProductPolicy
{
    public function viewActive(User $user): bool
    {
        return $user->hasPermission('loans.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('loan_products.manage');
    }
}
