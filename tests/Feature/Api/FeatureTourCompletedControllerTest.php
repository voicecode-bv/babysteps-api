<?php

use App\Models\User;

it('requires authentication', function () {
    $this->postJson('/api/feature-tour/completed')->assertUnauthorized();
});

it('sets feature_tour_completed_at for the authenticated user', function () {
    $user = User::factory()->create([
        'feature_tour_started_at' => now()->subMinutes(5),
        'feature_tour_completed_at' => null,
    ]);

    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/feature-tour/completed')
        ->assertNoContent();

    expect($user->fresh()->feature_tour_completed_at->timestamp)->toBe(now()->timestamp);
});

it('back-fills started_at if it was never set', function () {
    $user = User::factory()->create([
        'feature_tour_started_at' => null,
        'feature_tour_completed_at' => null,
    ]);

    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/feature-tour/completed')
        ->assertNoContent();

    $fresh = $user->fresh();
    expect($fresh->feature_tour_started_at->timestamp)->toBe(now()->timestamp);
    expect($fresh->feature_tour_completed_at->timestamp)->toBe(now()->timestamp);
});

it('updates the timestamp when called again', function () {
    $user = User::factory()->create([
        'feature_tour_started_at' => now()->subDays(2),
        'feature_tour_completed_at' => now()->subDays(1),
    ]);

    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/feature-tour/completed')
        ->assertNoContent();

    expect($user->fresh()->feature_tour_completed_at->timestamp)->toBe(now()->timestamp);
});
