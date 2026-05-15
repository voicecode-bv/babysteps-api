<?php

namespace App\Listeners;

use App\Enums\SubscriptionEventType;
use App\Events\SubscriptionStatusChanged;
use App\Notifications\SubscriptionStarted;

class SendSubscriptionStartedMail
{
    public function handle(SubscriptionStatusChanged $event): void
    {
        if ($event->cause !== SubscriptionEventType::Started) {
            return;
        }

        $user = $event->subscription->user;

        if ($user === null) {
            return;
        }

        $user->notify(new SubscriptionStarted);
    }
}
