<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a password reset notification with a frontend url', function () {
    config()->set('app.frontend_url', 'https://innerr.app');

    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/auth/forgot-password', [
        'email' => 'jane@example.com',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);

        expect($mail->actionUrl)->toStartWith('https://innerr.app/password-reset?token=');
        expect($mail->actionUrl)->toContain('&email=jane%40example.com');

        return true;
    });
});

it('returns 200 when the email is unknown to avoid enumeration', function () {
    Notification::fake();

    $this->postJson('/api/auth/forgot-password', [
        'email' => 'nobody@example.com',
    ])->assertOk();

    Notification::assertNothingSent();
});

it('validates the email field on forgot-password', function () {
    $this->postJson('/api/auth/forgot-password', [
        'email' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('resets the password with a valid token', function () {
    Event::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);
    $token = app('auth.password.broker')->createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'jane@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();

    Event::assertDispatched(PasswordReset::class);
});

it('rejects an invalid reset token', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/auth/reset-password', [
        'token' => 'bogus-token',
        'email' => 'jane@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('requires password confirmation on reset', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $token = app('auth.password.broker')->createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'jane@example.com',
        'password' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('requires token, email and password on reset', function () {
    $this->postJson('/api/auth/reset-password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'email', 'password']);
});
