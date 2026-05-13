<?php

use App\Listeners\PruneInvalidFcmTokens;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;

function failureEvent(User $user, SendReport $report, string $channel = FcmChannel::class): NotificationFailed
{
    return new NotificationFailed($user, new class extends Notification {}, $channel, ['report' => $report]);
}

function unknownTokenReport(string $token): SendReport
{
    return SendReport::failure(
        MessageTarget::with(MessageTarget::TOKEN, $token),
        NotFound::becauseTokenNotFound($token),
    );
}

it('deletes the device token when the FCM report flags it as unknown', function () {
    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'dead-token', 'last_used_at' => now()]);
    $user->deviceTokens()->create(['token' => 'live-token', 'last_used_at' => now()]);

    (new PruneInvalidFcmTokens)->handle(failureEvent($user, unknownTokenReport('dead-token')));

    expect($user->deviceTokens()->pluck('token')->all())->toBe(['live-token']);
});

it('ignores failures from other notification channels', function () {
    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'dead-token', 'last_used_at' => now()]);

    (new PruneInvalidFcmTokens)->handle(
        failureEvent($user, unknownTokenReport('dead-token'), channel: 'mail'),
    );

    expect(DeviceToken::query()->where('token', 'dead-token')->exists())->toBeTrue();
});

it('keeps the token when the FCM report has no token-related failure', function () {
    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'live-token', 'last_used_at' => now()]);

    $report = SendReport::success(
        MessageTarget::with(MessageTarget::TOKEN, 'live-token'),
        ['name' => 'projects/x/messages/123'],
    );

    (new PruneInvalidFcmTokens)->handle(failureEvent($user, $report));

    expect(DeviceToken::query()->where('token', 'live-token')->exists())->toBeTrue();
});

it('is wired up via the application event dispatcher', function () {
    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'dead-token', 'last_used_at' => now()]);

    event(failureEvent($user, unknownTokenReport('dead-token')));

    expect(DeviceToken::query()->where('token', 'dead-token')->exists())->toBeFalse();
});
