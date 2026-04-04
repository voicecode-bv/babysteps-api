<?php

namespace Database\Factories;

use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CircleInvitation>
 */
class CircleInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'circle_id' => Circle::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(64),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'accepted_at' => now(),
        ]);
    }
}
