<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

class NoticePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin') && $user->hasPermission('notices.manage');
    }

    public function view(User $user, Notice $notice): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Notice $notice): bool
    {
        return $this->viewAny($user);
    }

    public function publish(User $user, Notice $notice): bool
    {
        return $this->viewAny($user);
    }

    public function archive(User $user, Notice $notice): bool
    {
        return $this->viewAny($user);
    }
}
