<?php

use App\Models\User;
use App\Notifications\OnboardingCompleted;
use Illuminate\Support\Facades\App;

it('renders the welcome mail subject and body for each supported locale', function (string $locale, string $subject, string $greeting) {
    App::setLocale($locale);

    $user = User::factory()->create(['name' => 'Jordan', 'locale' => $locale]);

    $notification = new OnboardingCompleted;
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe($subject)
        ->and($mail->viewData['body'])->toContain($greeting);
})->with([
    'nl' => ['nl', 'Welkom bij Innerr, Jordan!', 'Welkom bij Innerr, Jordan!'],
    'en' => ['en', 'Welcome to Innerr, Jordan!', 'Welcome to Innerr, Jordan!'],
    'fr' => ['fr', 'Bienvenue sur Innerr, Jordan !', 'Bienvenue sur Innerr, Jordan !'],
]);
