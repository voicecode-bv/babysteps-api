<?php

use App\Enums\InvitationStatus;
use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\User;
use App\Notifications\CircleInvitationReceivedNotification;

it('can list pending invitations for the authenticated user', function () {
    $user = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $user->id,
        'status' => InvitationStatus::Pending,
    ]);

    // Create an accepted invitation that should not show up
    CircleInvitation::factory()->create([
        'user_id' => $user->id,
        'status' => InvitationStatus::Accepted,
    ]);

    // Create an invitation for another user that should not show up
    CircleInvitation::factory()->create([
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($user)
        ->getJson('/api/circle-invitations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $invitation->id);
});

it('can accept a pending invitation', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($user)
        ->postJson("/api/circle-invitations/{$invitation->id}/accept")
        ->assertOk()
        ->assertJsonPath('message', 'Invitation accepted.');

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Accepted);

    $this->assertDatabaseHas('circle_user', [
        'circle_id' => $circle->id,
        'user_id' => $user->id,
    ]);
});

it('can decline a pending invitation', function () {
    $user = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $user->id,
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($user)
        ->postJson("/api/circle-invitations/{$invitation->id}/decline")
        ->assertOk()
        ->assertJsonPath('message', 'Invitation declined.');

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Declined);
});

it('cannot accept another users invitation', function () {
    $invitation = CircleInvitation::factory()->create([
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson("/api/circle-invitations/{$invitation->id}/accept")
        ->assertForbidden();
});

it('cannot decline another users invitation', function () {
    $invitation = CircleInvitation::factory()->create([
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson("/api/circle-invitations/{$invitation->id}/decline")
        ->assertForbidden();
});

it('cannot accept an already accepted invitation', function () {
    $user = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $user->id,
        'status' => InvitationStatus::Accepted,
    ]);

    $this->actingAs($user)
        ->postJson("/api/circle-invitations/{$invitation->id}/accept")
        ->assertForbidden();
});

it('cannot accept an already declined invitation', function () {
    $user = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $user->id,
        'status' => InvitationStatus::Declined,
    ]);

    $this->actingAs($user)
        ->postJson("/api/circle-invitations/{$invitation->id}/accept")
        ->assertForbidden();
});

it('lets the circle owner cancel a pending invitation', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'inviter_id' => $owner->id,
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/circles/{$circle->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    expect(CircleInvitation::find($invitation->id))->toBeNull();
});

it('lets the inviter cancel an invitation they sent', function () {
    $owner = User::factory()->create();
    $inviter = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create(['members_can_invite' => true]);
    $circle->members()->attach($inviter);

    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'inviter_id' => $inviter->id,
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($inviter)
        ->deleteJson("/api/circles/{$circle->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    expect(CircleInvitation::find($invitation->id))->toBeNull();
});

it('forbids cancelling an invitation the user did not send and does not own the circle for', function () {
    $owner = User::factory()->create();
    $inviter = User::factory()->create();
    $other = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create(['members_can_invite' => true]);
    $circle->members()->attach([$inviter->id, $other->id]);

    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'inviter_id' => $inviter->id,
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($other)
        ->deleteJson("/api/circles/{$circle->id}/invitations/{$invitation->id}")
        ->assertForbidden();

    expect(CircleInvitation::find($invitation->id))->not->toBeNull();
});

it('returns 404 when cancelling an invitation that does not belong to the circle', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    $otherCircle = Circle::factory()->for($owner)->create();

    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $otherCircle->id,
        'inviter_id' => $owner->id,
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/circles/{$circle->id}/invitations/{$invitation->id}")
        ->assertNotFound();
});

it('marks the received notification as read when accepting an invitation', function () {
    $user = User::factory()->create();
    $inviter = User::factory()->create();
    $circle = Circle::factory()->for($inviter)->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'inviter_id' => $inviter->id,
        'status' => InvitationStatus::Pending,
    ]);

    $user->notify(new CircleInvitationReceivedNotification($invitation, $inviter->name));

    // Unrelated received notification for a different invitation should stay unread
    $otherInvitation = CircleInvitation::factory()->create([
        'user_id' => $user->id,
        'status' => InvitationStatus::Pending,
    ]);
    $user->notify(new CircleInvitationReceivedNotification($otherInvitation, $inviter->name));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->postJson("/api/circle-invitations/{$invitation->id}/accept")
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(1);
    expect($user->unreadNotifications()->first()->data['invitation_id'])->toBe($otherInvitation->id);
});

it('marks the received notification as read when declining an invitation', function () {
    $user = User::factory()->create();
    $inviter = User::factory()->create();
    $circle = Circle::factory()->for($inviter)->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'inviter_id' => $inviter->id,
        'status' => InvitationStatus::Pending,
    ]);

    $user->notify(new CircleInvitationReceivedNotification($invitation, $inviter->name));

    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)
        ->postJson("/api/circle-invitations/{$invitation->id}/decline")
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('can accept a new invitation when a previous one was already accepted for the same circle', function () {
    $user = User::factory()->create();
    $circle = Circle::factory()->create();

    // Old accepted invitation
    CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'status' => InvitationStatus::Accepted,
    ]);

    // New pending invitation for the same circle
    $newInvitation = CircleInvitation::factory()->create([
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'status' => InvitationStatus::Pending,
    ]);

    $this->actingAs($user)
        ->postJson("/api/circle-invitations/{$newInvitation->id}/accept")
        ->assertOk()
        ->assertJsonPath('message', 'Invitation accepted.');

    expect($newInvitation->fresh()->status)->toBe(InvitationStatus::Accepted);

    // Old record should be cleaned up
    expect(CircleInvitation::where('circle_id', $circle->id)
        ->where('user_id', $user->id)
        ->count())->toBe(1);
});
