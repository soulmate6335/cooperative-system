<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'member_number' => 'MEM-'.strtoupper(Str::random(10)),
            'joined_at' => now(),
            'status' => 'active',
            'membership_type' => 'regular',
        ];
    }

    public function joinedMonthsAgo(int $months): static
    {
        return $this->state(fn (array $attributes) => [
            'joined_at' => now()->subMonths($months),
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
