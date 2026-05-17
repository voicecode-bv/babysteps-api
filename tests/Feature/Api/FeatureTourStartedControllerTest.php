<?php

use App\Models\User;

it('requires authentication', function () {
    $this->postJson('/api/feature-tour/started')->assertUnauthorized();
});

it('sets feature_tour_started_at the first time it is called', function () {
    $user = User::factory()->create(['feature_tour_started_at' => null]);

    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/feature-tour/started')
        ->assertNoContent();

    expect($user->fresh()->feature_tour_started_at->timestamp)->toBe(now()->timestamp);
});

it('keeps the original timestamp on repeat calls', function () {
    $original = now()->subDays(2);
    $user = User::factory()->create(['feature_tour_started_at' => $original]);

    $this->travelTo(now()->addHour());

    $this->actingAs($user)
        ->postJson('/api/feature-tour/started')
        ->assertNoContent();

    expect($user->fresh()->feature_tour_started_at->timestamp)->toBe($original->timestamp);
});
