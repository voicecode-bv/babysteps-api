<?php

use App\Models\Circle;
use App\Models\User;

it('allows the owner to enable members_can_invite', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create(['members_can_invite' => false]);

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [
            'members_can_invite' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.members_can_invite', true);

    expect($circle->fresh()->members_can_invite)->toBeTrue();
});

it('allows the owner to disable members_can_invite', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create(['members_can_invite' => true]);

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [
            'members_can_invite' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.members_can_invite', false);

    expect($circle->fresh()->members_can_invite)->toBeFalse();
});

it('forbids non-owners from updating circle settings', function () {
    $circle = Circle::factory()->create();
    $member = User::factory()->create();
    $circle->members()->attach($member);

    $this->actingAs($member)
        ->putJson("/api/circles/{$circle->id}/settings", [
            'members_can_invite' => true,
        ])
        ->assertForbidden();
});

it('validates that members_can_invite is required', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('members_can_invite');
});

it('validates that members_can_invite must be a boolean', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [
            'members_can_invite' => 'invalid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('members_can_invite');
});
