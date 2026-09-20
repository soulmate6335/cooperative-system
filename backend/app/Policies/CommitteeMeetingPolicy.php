<?php

namespace App\Policies;

use App\Models\User;

class CommitteeMeetingPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasPermission('committee_meetings.manage');
    }
}
