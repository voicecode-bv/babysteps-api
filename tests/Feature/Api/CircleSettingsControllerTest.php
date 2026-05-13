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

it('allows the owner to toggle members_can_view_members on its own', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create([
        'members_can_invite' => true,
        'members_can_view_members' => true,
    ]);

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [
            'members_can_view_members' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.members_can_view_members', false)
        ->assertJsonPath('data.members_can_invite', true);

    $fresh = $circle->fresh();
    expect($fresh->members_can_view_members)->toBeFalse();
    expect($fresh->members_can_invite)->toBeTrue();
});

it('allows the owner to toggle members_can_download on its own', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create([
        'members_can_download' => false,
    ]);

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [
            'members_can_download' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.members_can_download', true);

    expect($circle->fresh()->members_can_download)->toBeTrue();
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

it('requires at least one setting in the payload', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'members_can_invite',
            'members_can_view_members',
            'members_can_download',
        ]);
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

it('validates that members_can_download must be a boolean', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/circles/{$circle->id}/settings", [
            'members_can_download' => 'invalid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('members_can_download');
});
