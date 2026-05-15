<?php

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionStarted;
use App\Services\Subscriptions\SubscriptionStateMachine;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Plan::factory()->free()->create();
});

it('notifies the user when a subscription transitions to active via Started', function () {
    Notification::fake();

    $user = User::factory()->create();
    $subscription = Subscription::factory()
        ->for($user)
        ->state(['status' => SubscriptionStatus::Expired])
        ->create();

    (new SubscriptionStateMachine)->apply($subscription, SubscriptionEventType::Started);

    Notification::assertSentTo($user, SubscriptionStarted::class);
});

it('does not send the welcome mail when the cause is a renewal', function () {
    Notification::fake();

    $user = User::factory()->create();
    $subscription = Subscription::factory()
        ->for($user)
        ->state(['status' => SubscriptionStatus::Expired])
        ->create();

    (new SubscriptionStateMachine)->apply($subscription, SubscriptionEventType::Renewed);

    Notification::assertNothingSentTo($user);
});

it('renders the rendered template subject and donate url', function (string $locale, string $subject, string $greeting, string $donateUrl) {
    App::setLocale($locale);

    $user = User::factory()->create(['name' => 'Jordan', 'locale' => $locale]);

    $notification = new SubscriptionStarted;
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe($subject)
        ->and($mail->viewData['body'])->toContain($greeting)
        ->and($mail->viewData['body'])->toContain($donateUrl);
})->with([
    'nl' => ['nl', 'Bedankt voor je abonnement op Innerr', 'Bedankt Jordan!', 'https://innerr.app/nl/doneren/'],
    'en' => ['en', 'Thank you for subscribing to Innerr', 'Thank you Jordan!', 'https://innerr.app/en/donate/'],
    'fr' => ['fr', 'Merci pour votre abonnement à Innerr', 'Merci Jordan !', 'https://innerr.app/fr/don/'],
]);
