<?php

use App\Models\User;
use App\Notifications\OnboardingCompleted;
use Illuminate\Support\Facades\Notification;

it('requires authentication', function () {
    $this->postJson('/api/onboarding/complete')->assertUnauthorized();
});

it('sets the onboarded_at timestamp for the authenticated user', function () {
    $user = User::factory()->create(['onboarded_at' => null]);

    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/onboarding/complete')
        ->assertNoContent();

    expect($user->fresh()->onboarded_at->timestamp)->toBe(now()->timestamp);
});

it('updates the timestamp when called again', function () {
    $user = User::factory()->create(['onboarded_at' => now()->subDays(5)]);

    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/onboarding/complete')
        ->assertNoContent();

    expect($user->fresh()->onboarded_at->timestamp)->toBe(now()->timestamp);
});

it('sends the welcome mail the first time onboarding completes', function () {
    Notification::fake();

    $user = User::factory()->create(['onboarded_at' => null]);

    $this->actingAs($user)
        ->postJson('/api/onboarding/complete')
        ->assertNoContent();

    Notification::assertSentTo($user, OnboardingCompleted::class);
});

it('does not send the welcome mail when onboarding was already completed', function () {
    Notification::fake();

    $user = User::factory()->create(['onboarded_at' => now()->subDays(5)]);

    $this->actingAs($user)
        ->postJson('/api/onboarding/complete')
        ->assertNoContent();

    Notification::assertNothingSentTo($user);
});
