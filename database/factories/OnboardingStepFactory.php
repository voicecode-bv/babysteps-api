<?php

namespace Database\Factories;

use App\Enums\OnboardingStep as OnboardingStepEnum;
use App\Models\OnboardingStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingStep>
 */
class OnboardingStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'step' => fake()->randomElement(OnboardingStepEnum::cases()),
            'completed_at' => now(),
        ];
    }
}
