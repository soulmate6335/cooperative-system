<?php

namespace App\Policies;

use App\Models\User;

class OrganizationContentPolicy
{
    public function view(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('settings.manage');
    }

    public function update(User $user): bool
    {
        return $this->view($user);
    }
}
