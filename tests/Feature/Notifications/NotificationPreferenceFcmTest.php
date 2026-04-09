<?php

use App\Enums\NotificationPreference;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentLiked;
use App\Notifications\NewCirclePost;
use App\Notifications\PostCommented;
use App\Notifications\PostLiked;
use NotificationChannels\Fcm\FcmChannel;

it('includes fcm channel when preference is enabled', function () {
    $user = new User(['fcm_token' => 'token']);

    $notification = new PostLiked(new User, new Post);

    expect($notification->via($user))->toContain(FcmChannel::class);
});

it('excludes fcm channel when preference is disabled', function () {
    $preferences = NotificationPreference::defaults();
    $preferences['post_liked'] = false;

    $user = new User(['fcm_token' => 'token', 'notification_preferences' => $preferences]);

    $notification = new PostLiked(new User, new Post);

    expect($notification->via($user))->not->toContain(FcmChannel::class)
        ->and($notification->via($user))->toContain('database');
});

it('excludes fcm for post_commented when disabled', function () {
    $preferences = NotificationPreference::defaults();
    $preferences['post_commented'] = false;

    $user = new User(['fcm_token' => 'token', 'notification_preferences' => $preferences]);

    $notification = new PostCommented(new User, new Post, new Comment);

    expect($notification->via($user))->not->toContain(FcmChannel::class);
});

it('excludes fcm for comment_liked when disabled', function () {
    $preferences = NotificationPreference::defaults();
    $preferences['comment_liked'] = false;

    $user = new User(['fcm_token' => 'token', 'notification_preferences' => $preferences]);

    $notification = new CommentLiked(new User, new Comment);

    expect($notification->via($user))->not->toContain(FcmChannel::class);
});

it('excludes fcm for new_circle_post when disabled', function () {
    $preferences = NotificationPreference::defaults();
    $preferences['new_circle_post'] = false;

    $user = new User(['fcm_token' => 'token', 'notification_preferences' => $preferences]);

    $notification = new NewCirclePost(new User, new Post);

    expect($notification->via($user))->not->toContain(FcmChannel::class);
});

it('defaults to fcm enabled when no preferences are stored', function () {
    $user = new User(['fcm_token' => 'token']);

    $notifications = [
        new PostLiked(new User, new Post),
        new PostCommented(new User, new Post, new Comment),
        new CommentLiked(new User, new Comment),
        new NewCirclePost(new User, new Post),
    ];

    foreach ($notifications as $notification) {
        expect($notification->via($user))->toContain(FcmChannel::class);
    }
});
