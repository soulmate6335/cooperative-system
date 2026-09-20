<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\LoanInvestigation;
use App\Models\User;

class LoanApplicationPolicy
{
    public function viewAnyAdmin(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('loans.view');
    }

    public function view(User $user, LoanApplication $application): bool
    {
        if ($application->member()->where('user_id', $user->id)->exists()) {
            return $user->hasPermission('loans.apply');
        }

        return $user->hasPermission('loans.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('loans.apply');
    }

    public function submit(User $user, LoanApplication $application): bool
    {
        return $application->member()->where('user_id', $user->id)->exists()
            && $user->hasPermission('loans.apply');
    }

    public function request(User $user, LoanApplication $application): bool
    {
        return $application->member()->where('user_id', $user->id)->exists()
            && $user->hasPermission('loans.apply');
    }

    public function review(User $user, LoanApplication $application): bool
    {
        if ($user->hasRole('admin', 'super_admin')) {
            return $user->hasPermission('loans.investigate');
        }

        $investigation = $application->investigation;

        return $investigation instanceof LoanInvestigation
            && $investigation->assigned_to === $user->id
            && $user->hasPermission('loans.investigate');
    }

    public function cancel(User $user, LoanApplication $application): bool
    {
        return $this->submit($user, $application);
    }

    public function adminCancel(User $user, LoanApplication $application): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('loans.view');
    }

    public function approve(User $user, LoanApplication $application): bool
    {
        return $application->member->user_id !== $user->id
            && $application->status === 'pending_admin_decision'
            && $user->hasRole('admin', 'super_admin')
            && $user->hasPermission('loans.approve');
    }

    public function reject(User $user, LoanApplication $application): bool
    {
        return $application->member->user_id !== $user->id
            && $user->hasRole('admin', 'super_admin')
            && $user->hasPermission('loans.reject');
    }
}
