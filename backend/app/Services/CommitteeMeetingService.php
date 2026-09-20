<?php

namespace App\Services;

use App\Models\CommitteeMeeting;

class CommitteeMeetingService
{
    public function create(array $data, string $createdByUserId): CommitteeMeeting
    {
        return CommitteeMeeting::create([...$data, 'created_by' => $createdByUserId])->fresh();
    }

    public function update(CommitteeMeeting $meeting, array $data): CommitteeMeeting
    {
        $meeting->update($data);

        return $meeting->fresh();
    }
}
