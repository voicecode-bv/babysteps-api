<?php

use App\Models\Circle;
use App\Models\User;

it('returns circles owned by the user with is_owner true', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson('/api/circles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $circle->id)
        ->assertJsonPath('data.0.is_owner', true);
});

it('also returns circles the user is a member of with is_owner false', function () {
    $user = User::factory()->create();
    $owned = Circle::factory()->for($user)->create();
    $joined = Circle::factory()->create();
    $joined->members()->attach($user);

    $response = $this->actingAs($user)
        ->getJson('/api/circles')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$owned->id]['is_owner'])->toBeTrue();
    expect($byId[$joined->id]['is_owner'])->toBeFalse();
});

it('does not return circles the user has no relation to', function () {
    $user = User::factory()->create();
    Circle::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/circles')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('does not duplicate a circle if the user is both owner and member', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create();
    $circle->members()->attach($user);

    $this->actingAs($user)
        ->getJson('/api/circles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_owner', true);
});
