<?php

use App\Models\Circle;
use App\Models\Person;
use App\Models\User;

it('attaches a newly registered user to every circle flagged auto_add_new_users', function () {
    $autoCircleA = Circle::factory()->create(['auto_add_new_users' => true]);
    $autoCircleB = Circle::factory()->create(['auto_add_new_users' => true]);
    $regularCircle = Circle::factory()->create(['auto_add_new_users' => false]);

    $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'username' => 'janedoe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'testing',
    ])->assertCreated();

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($autoCircleA->members()->whereKey($user->id)->exists())->toBeTrue();
    expect($autoCircleB->members()->whereKey($user->id)->exists())->toBeTrue();
    expect($regularCircle->members()->whereKey($user->id)->exists())->toBeFalse();

    $person = Person::where('user_id', $user->id)->firstOrFail();
    expect($person->circles()->whereKey($autoCircleA->id)->exists())->toBeTrue();
    expect($person->circles()->whereKey($autoCircleB->id)->exists())->toBeTrue();
});

it('does not attach the user to an auto-add circle they own', function () {
    $owner = User::factory()->create();
    $ownedAutoCircle = Circle::factory()->for($owner)->create(['auto_add_new_users' => true]);

    expect($ownedAutoCircle->members()->whereKey($owner->id)->exists())->toBeFalse();
});

it('attaches users created via the User factory too', function () {
    $autoCircle = Circle::factory()->create(['auto_add_new_users' => true]);

    $user = User::factory()->create();

    expect($autoCircle->members()->whereKey($user->id)->exists())->toBeTrue();
});

it('does nothing when no circles are flagged', function () {
    Circle::factory()->create(['auto_add_new_users' => false]);

    $user = User::factory()->create();

    expect(Person::where('user_id', $user->id)->exists())->toBeFalse();
});
