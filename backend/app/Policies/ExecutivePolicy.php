<?php

namespace App\Policies;

use App\Models\Executive;
use App\Models\User;

class ExecutivePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('executives.manage');
    }

    public function view(User $user, Executive $executive): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Executive $executive): bool
    {
        return $this->viewAny($user);
    }

    public function visibility(User $user, Executive $executive): bool
    {
        return $this->viewAny($user);
    }
}
