<?php

namespace Database\Factories;

use App\Models\Circle;
use App\Models\CircleInviteLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CircleInviteLink>
 */
class CircleInviteLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'circle_id' => Circle::factory(),
            'created_by_user_id' => User::factory(),
            'token' => Str::random(43),
            'expires_at' => now()->addDays(7),
            'max_uses' => null,
            'uses_count' => 0,
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }

    public function maxedOut(): static
    {
        return $this->state(['max_uses' => 1, 'uses_count' => 1]);
    }
}
