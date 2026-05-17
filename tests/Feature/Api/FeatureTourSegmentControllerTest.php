<?php

use App\Enums\FeatureTourSegment;
use App\Models\FeatureTourStep;
use App\Models\User;

it('requires authentication', function () {
    $this->postJson('/api/feature-tour/segments/feed/completed')
        ->assertUnauthorized();
});

it('records a completed segment for the authenticated user', function () {
    $user = User::factory()->create();

    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/feature-tour/segments/feed/completed')
        ->assertNoContent();

    $step = FeatureTourStep::query()
        ->where('user_id', $user->id)
        ->where('step', FeatureTourSegment::Feed)
        ->sole();

    expect($step->completed_at->timestamp)->toBe(now()->timestamp);
});

it('is idempotent and keeps the original completed_at on re-post', function () {
    $user = User::factory()->create();

    $first = now()->subHour();
    $this->travelTo($first);

    $this->actingAs($user)
        ->postJson('/api/feature-tour/segments/circles/completed')
        ->assertNoContent();

    $this->travelTo(now()->addHour());

    $this->actingAs($user)
        ->postJson('/api/feature-tour/segments/circles/completed')
        ->assertNoContent();

    $rows = FeatureTourStep::query()
        ->where('user_id', $user->id)
        ->where('step', FeatureTourSegment::Circles)
        ->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->completed_at->timestamp)->toBe($first->timestamp);
});

it('returns 404 for an unknown segment', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/feature-tour/segments/nope/completed')
        ->assertNotFound();
});

it('isolates segments per user', function () {
    [$alice, $bob] = User::factory()->count(2)->create();

    $this->actingAs($alice)
        ->postJson('/api/feature-tour/segments/profile/completed')
        ->assertNoContent();

    expect(FeatureTourStep::where('user_id', $alice->id)->count())->toBe(1);
    expect(FeatureTourStep::where('user_id', $bob->id)->count())->toBe(0);
});
