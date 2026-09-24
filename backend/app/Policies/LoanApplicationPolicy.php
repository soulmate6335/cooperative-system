<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\LoanInvestigation;
use App\Models\User;
use App\Services\LoanInvestigationService;

class LoanApplicationPolicy
{
    public function __construct(private readonly LoanInvestigationService $investigationService) {}

    /**
     * Committee detail access: the assigned investigator, anyone with review
     * rights over an application still sitting in the open committee queue,
     * or an administrator.
     */
    public function committeeView(User $user, LoanApplication $application): bool
    {
        if (! $user->hasPermission('loans.investigate')) {
            return false;
        }

        if ($user->hasRole('admin', 'super_admin')) {
            return true;
        }

        $investigation = $application->investigation;

        if ($investigation instanceof LoanInvestigation && $investigation->assigned_to === $user->id) {
            return true;
        }

        return $this->investigationService->isReadyForCommittee($application);
    }

    /**
     * A committee officer may start (self-assign) an investigation on an
     * application that is still ready for committee review.
     */
    public function startInvestigation(User $user, LoanApplication $application): bool
    {
        if (! $user->hasPermission('loans.investigate')) {
            return false;
        }

        if ($user->hasRole('admin', 'super_admin')) {
            return true;
        }

        return $this->investigationService->isReadyForCommittee($application);
    }

    public function viewAnyAdmin(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('loans.view');
    }

    public function view(User $user, LoanApplication $application): bool
    {
        if ($application->member()->where('user_id', $user->id)->exists()) {
            return $user->hasPermission('loans.apply');
        }

        // Members may only access their own applications. Staff roles
        // (committee, finance, admin) with the loan review permission see
        // the administrative view through their dedicated endpoints.
        if ($user->hasRole('member')) {
            return false;
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

    public function update(User $user, LoanApplication $application): bool
    {
        return $this->submit($user, $application);
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
