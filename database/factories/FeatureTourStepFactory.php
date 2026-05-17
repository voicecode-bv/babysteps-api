<?php

namespace Database\Factories;

use App\Enums\FeatureTourSegment;
use App\Models\FeatureTourStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureTourStep>
 */
class FeatureTourStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'step' => fake()->randomElement(FeatureTourSegment::cases()),
            'completed_at' => now(),
        ];
    }
}
