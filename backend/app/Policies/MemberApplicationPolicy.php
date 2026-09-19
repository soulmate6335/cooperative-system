<?php

namespace App\Policies;

use App\Models\MemberApplication;
use App\Models\User;

class MemberApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('members.review');
    }

    public function view(User $user, MemberApplication $application): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, MemberApplication $application): bool
    {
        return $application->user_id !== $user->id
            && $application->status === 'pending'
            && $user->hasRole('admin', 'super_admin')
            && $user->hasPermission('members.approve');
    }

    public function reject(User $user, MemberApplication $application): bool
    {
        return $application->user_id !== $user->id
            && $application->status === 'pending'
            && $user->hasRole('admin', 'super_admin')
            && $user->hasPermission('members.reject');
    }
}
