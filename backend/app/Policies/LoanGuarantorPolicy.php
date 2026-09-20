<?php

namespace App\Policies;

use App\Models\LoanGuarantor;
use App\Models\User;

class LoanGuarantorPolicy
{
    public function respond(User $user, LoanGuarantor $guarantorRequest): bool
    {
        return $guarantorRequest->guarantorMember->user_id === $user->id
            && $user->hasPermission('loans.guarantee');
    }

    public function cancelRequest(User $user, LoanGuarantor $guarantorRequest): bool
    {
        return $guarantorRequest->application->member()->where('user_id', $user->id)->exists()
            && $user->hasPermission('loans.apply');
    }
}
