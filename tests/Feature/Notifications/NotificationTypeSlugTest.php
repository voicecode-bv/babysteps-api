<?php

use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\User;
use App\Notifications\CircleInvitationAcceptedNotification;
use App\Notifications\CircleInvitationReceivedNotification;
use App\Notifications\CircleMemberInvitedByMemberNotification;

it('stores CircleInvitationAcceptedNotification with a slug type', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $circle = Circle::factory()->for($inviter)->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $invitee->id,
        'inviter_id' => $inviter->id,
        'circle_id' => $circle->id,
    ]);

    $inviter->notify(new CircleInvitationAcceptedNotification($invitation, $invitee->name));

    expect($inviter->notifications()->first()->type)->toBe('circle-invitation-accepted');
});

it('stores CircleMemberInvitedByMemberNotification with a slug type', function () {
    $inviter = User::factory()->create();
    $member = User::factory()->create();
    $invitee = User::factory()->create();
    $circle = Circle::factory()->for($inviter)->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $invitee->id,
        'inviter_id' => $inviter->id,
        'circle_id' => $circle->id,
    ]);

    $member->notify(new CircleMemberInvitedByMemberNotification($invitation, $inviter->name, $invitee->name));

    expect($member->notifications()->first()->type)->toBe('circle-member-invited-by-member');
});

it('stores CircleInvitationReceivedNotification with a slug type', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $circle = Circle::factory()->for($inviter)->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $invitee->id,
        'inviter_id' => $inviter->id,
        'circle_id' => $circle->id,
    ]);

    $invitee->notify(new CircleInvitationReceivedNotification($invitation, $inviter->name));

    expect($invitee->notifications()->first()->type)->toBe('circle-invitation-received');
});
