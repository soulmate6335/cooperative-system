<?php

namespace Database\Factories;

use App\Models\CommitteeMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeMeeting>
 */
class CommitteeMeetingFactory extends Factory
{
    protected $model = CommitteeMeeting::class;

    public function definition(): array
    {
        return [
            'meeting_date' => now()->addDays(21),
            'meeting_type' => 'loan_committee',
            'cutoff_days' => 14,
            'status' => 'scheduled',
            'notes' => null,
        ];
    }
}
