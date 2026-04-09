<?php

use App\Enums\NotificationPreference;
use App\Models\User;

it('returns default preferences when none are set', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/notification-preferences')
        ->assertOk();

    expect($response->json('data'))->toBe(NotificationPreference::defaults());
});

it('returns stored preferences', function () {
    $preferences = NotificationPreference::defaults();
    $preferences['post_liked'] = false;

    $user = User::factory()->create(['notification_preferences' => $preferences]);

    $response = $this->actingAs($user)
        ->getJson('/api/notification-preferences')
        ->assertOk();

    expect($response->json('data.post_liked'))->toBeFalse()
        ->and($response->json('data.post_commented'))->toBeTrue();
});

it('can update notification preferences', function () {
    $user = User::factory()->create();

    $payload = NotificationPreference::defaults();
    $payload['post_liked'] = false;
    $payload['comment_liked'] = false;

    $this->actingAs($user)
        ->putJson('/api/notification-preferences', $payload)
        ->assertOk()
        ->assertJsonPath('data.post_liked', false)
        ->assertJsonPath('data.comment_liked', false)
        ->assertJsonPath('data.post_commented', true);

    expect($user->fresh()->notification_preferences['post_liked'])->toBeFalse();
    expect($user->fresh()->notification_preferences['comment_liked'])->toBeFalse();
});

it('validates all preference keys are required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/notification-preferences', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(array_column(NotificationPreference::cases(), 'value'));
});

it('validates preference values must be booleans', function () {
    $user = User::factory()->create();

    $payload = collect(NotificationPreference::cases())
        ->mapWithKeys(fn (NotificationPreference $case) => [$case->value => 'invalid'])
        ->all();

    $this->actingAs($user)
        ->putJson('/api/notification-preferences', $payload)
        ->assertUnprocessable();
});

it('requires authentication', function () {
    $this->getJson('/api/notification-preferences')->assertUnauthorized();
    $this->putJson('/api/notification-preferences')->assertUnauthorized();
});
