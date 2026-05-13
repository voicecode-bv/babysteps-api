<?php

namespace App\Listeners;

use App\Models\DeviceToken;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;

class PruneInvalidFcmTokens
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FcmChannel::class) {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof SendReport) {
            return;
        }

        if (! $report->messageWasSentToUnknownToken() && ! $report->messageTargetWasInvalid()) {
            return;
        }

        $target = $report->target();

        if ($target->type() !== MessageTarget::TOKEN) {
            return;
        }

        DeviceToken::query()->where('token', $target->value())->delete();
    }
}
