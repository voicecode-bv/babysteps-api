<?php

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;

it('does not log the fcm token', function () {
    $user = User::factory()->create();

    Log::spy();

    $this->actingAs($user)
        ->postJson('/api/device-token', ['token' => 'sensitive-fcm-token'])
        ->assertNoContent();

    Log::shouldNotHaveReceived('info', function (string $message) {
        return str_contains($message, 'sensitive-fcm-token');
    });
});

it('stores the fcm token for the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/device-token', ['token' => 'fcm-test-token-123', 'platform' => 'ios'])
        ->assertNoContent();

    $token = $user->deviceTokens()->sole();

    expect($token->token)->toBe('fcm-test-token-123')
        ->and($token->platform)->toBe('ios')
        ->and($token->last_used_at)->not->toBeNull();
});

it('keeps multiple tokens for the same user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/device-token', ['token' => 'iphone-token'])
        ->assertNoContent();

    $this->actingAs($user)
        ->postJson('/api/device-token', ['token' => 'ipad-token'])
        ->assertNoContent();

    expect($user->deviceTokens()->pluck('token')->all())
        ->toContain('iphone-token', 'ipad-token');
});

it('refreshes last_used_at when the same token is re-registered', function () {
    $user = User::factory()->create();

    DeviceToken::create([
        'user_id' => $user->id,
        'token' => 'existing-token',
        'last_used_at' => now()->subWeek(),
    ]);

    $this->actingAs($user)
        ->postJson('/api/device-token', ['token' => 'existing-token'])
        ->assertNoContent();

    expect($user->deviceTokens()->count())->toBe(1)
        ->and($user->deviceTokens()->sole()->last_used_at->isToday())->toBeTrue();
});

it('reassigns a token to the latest user that registered it', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->postJson('/api/device-token', ['token' => 'shared-device-token'])
        ->assertNoContent();

    $this->actingAs($bob)
        ->postJson('/api/device-token', ['token' => 'shared-device-token'])
        ->assertNoContent();

    expect($alice->deviceTokens()->exists())->toBeFalse()
        ->and($bob->deviceTokens()->pluck('token')->all())->toBe(['shared-device-token']);
});

it('validates the token is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/device-token', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('requires authentication', function () {
    $this->postJson('/api/device-token', ['token' => 'some-token'])
        ->assertUnauthorized();
});
