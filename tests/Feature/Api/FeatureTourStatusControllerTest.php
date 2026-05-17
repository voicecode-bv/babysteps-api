<?php

use App\Enums\FeatureTourSegment;
use App\Models\FeatureTourStep;
use App\Models\User;

it('requires authentication', function () {
    $this->getJson('/api/feature-tour/status')->assertUnauthorized();
});

it('returns nulls and empty segments for a new user', function () {
    $user = User::factory()->create([
        'feature_tour_started_at' => null,
        'feature_tour_completed_at' => null,
    ]);

    $this->actingAs($user)
        ->getJson('/api/feature-tour/status')
        ->assertOk()
        ->assertExactJson([
            'started_at' => null,
            'completed_at' => null,
            'segments' => [],
        ]);
});

it('returns timestamps and completed segments for a user mid-tour', function () {
    $startedAt = now()->subMinutes(5);
    $user = User::factory()->create([
        'feature_tour_started_at' => $startedAt,
        'feature_tour_completed_at' => null,
    ]);

    FeatureTourStep::factory()->create([
        'user_id' => $user->id,
        'step' => FeatureTourSegment::Feed,
    ]);
    FeatureTourStep::factory()->create([
        'user_id' => $user->id,
        'step' => FeatureTourSegment::Circles,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/feature-tour/status')
        ->assertOk()
        ->json();

    expect($response['started_at'])->toBe($startedAt->toIso8601String());
    expect($response['completed_at'])->toBeNull();
    expect($response['segments'])->toEqualCanonicalizing(['feed', 'circles']);
});

it('isolates status per user', function () {
    $alice = User::factory()->create(['feature_tour_started_at' => now()]);
    $bob = User::factory()->create(['feature_tour_started_at' => null]);

    FeatureTourStep::factory()->create([
        'user_id' => $alice->id,
        'step' => FeatureTourSegment::Feed,
    ]);

    $this->actingAs($bob)
        ->getJson('/api/feature-tour/status')
        ->assertOk()
        ->assertJsonPath('started_at', null)
        ->assertJsonPath('segments', []);
});
