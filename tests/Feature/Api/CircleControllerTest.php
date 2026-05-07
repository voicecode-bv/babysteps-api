<?php

use App\Enums\InvitationStatus;
use App\Models\Circle;
use App\Models\CircleInvitation;
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

it('filters out circles where the not_member_username target is already a member or owner', function () {
    $user = User::factory()->create();
    $target = User::factory()->create(['username' => 'targetuser']);

    $invitable = Circle::factory()->for($user)->create();

    $alreadyMember = Circle::factory()->for($user)->create();
    $alreadyMember->members()->attach($target);

    $ownedByTarget = Circle::factory()->for($target)->create();
    $ownedByTarget->members()->attach($user);

    $response = $this->actingAs($user)
        ->getJson('/api/circles?not_member_username=targetuser')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.id'))->toBe($invitable->id);
});

it('returns all related circles when not_member_username does not match an existing user', function () {
    $user = User::factory()->create();
    Circle::factory()->for($user)->create();
    Circle::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson('/api/circles?not_member_username=nope-does-not-exist')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('allows a member to view a circle and its members', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $circle->members()->attach([$member->id, $other->id]);

    $this->actingAs($member)
        ->getJson("/api/circles/{$circle->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $circle->id)
        ->assertJsonPath('data.is_owner', false)
        ->assertJsonCount(3, 'data.members')
        ->assertJsonPath('data.members_count', 3);
});

it('includes the owner in the members list with is_owner flag', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    $member = User::factory()->create();
    $circle->members()->attach($member);

    $response = $this->actingAs($owner)
        ->getJson("/api/circles/{$circle->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.members')
        ->assertJsonPath('data.members_count', 2);

    $members = collect($response->json('data.members'))->keyBy('id');
    expect($members[$owner->id]['is_owner'])->toBeTrue();
    expect($members[$member->id]['is_owner'])->toBeFalse();
});

it('does not expose pending invitations to non-owner members', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    $member = User::factory()->create();
    $circle->members()->attach($member);

    CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'inviter_id' => $owner->id,
    ]);

    $this->actingAs($member)
        ->getJson("/api/circles/{$circle->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.pending_invitations');
});

it('exposes pending invitations to members when members_can_invite is true', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create(['members_can_invite' => true]);
    $member = User::factory()->create();
    $circle->members()->attach($member);

    CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'inviter_id' => $owner->id,
    ]);

    $this->actingAs($member)
        ->getJson("/api/circles/{$circle->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.pending_invitations');
});

it('forbids users with no relation from viewing a circle', function () {
    $circle = Circle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson("/api/circles/{$circle->id}")
        ->assertForbidden();
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

it('exposes a target users pending invitation when filtered by not_member_username', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create(['username' => 'targetuser']);

    $invitable = Circle::factory()->for($owner)->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $invitable->id,
        'user_id' => $target->id,
        'inviter_id' => $owner->id,
        'status' => InvitationStatus::Pending,
    ]);

    $stillEmpty = Circle::factory()->for($owner)->create();

    $response = $this->actingAs($owner)
        ->getJson('/api/circles?not_member_username=targetuser')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$invitable->id]['pending_invitations'])->toHaveCount(1);
    expect($byId[$invitable->id]['pending_invitations'][0]['id'])->toBe($invitation->id);
    expect($byId[$invitable->id]['pending_invitations'][0]['can_cancel'])->toBeTrue();
    expect($byId[$invitable->id]['pending_invitations'][0]['inviter_id'])->toBe($owner->id);
    expect($byId[$stillEmpty->id]['pending_invitations'])->toBeEmpty();
});

it('marks pending invitation as not cancellable for a member who is not the inviter', function () {
    $owner = User::factory()->create();
    $inviter = User::factory()->create();
    $member = User::factory()->create();
    $target = User::factory()->create(['username' => 'targetuser']);

    $circle = Circle::factory()->for($owner)->create(['members_can_invite' => true]);
    $circle->members()->attach([$inviter->id, $member->id]);

    CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'user_id' => $target->id,
        'inviter_id' => $inviter->id,
        'status' => InvitationStatus::Pending,
    ]);

    $response = $this->actingAs($member)
        ->getJson('/api/circles?not_member_username=targetuser')
        ->assertOk();

    $byId = collect($response->json('data'))->keyBy('id');
    expect($byId[$circle->id]['pending_invitations'][0]['can_cancel'])->toBeFalse();
});

it('does not include pending_invitations when not_member_username is not provided', function () {
    $user = User::factory()->create();
    Circle::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->getJson('/api/circles')
        ->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('pending_invitations');
});

it('ignores non-pending invitations when filtering by not_member_username', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create(['username' => 'targetuser']);
    $circle = Circle::factory()->for($owner)->create();

    CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'user_id' => $target->id,
        'inviter_id' => $owner->id,
        'status' => InvitationStatus::Declined,
    ]);

    $response = $this->actingAs($owner)
        ->getJson('/api/circles?not_member_username=targetuser')
        ->assertOk();

    expect($response->json('data.0.pending_invitations'))->toBeEmpty();
});
