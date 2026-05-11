<?php

use App\Jobs\AttachAllUsersToCircle;
use App\Models\Circle;
use App\Models\Person;
use App\Models\User;
use App\Services\MemberPersonSyncer;

it('attaches every non-owner user to the circle and creates missing person records', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    $userWithPerson = User::factory()->create();
    Person::factory()->create(['user_id' => $userWithPerson->id]);
    $userWithoutPerson = User::factory()->create();

    (new AttachAllUsersToCircle($circle))->handle(app(MemberPersonSyncer::class));

    expect($circle->members()->whereKey($userWithPerson->id)->exists())->toBeTrue();
    expect($circle->members()->whereKey($userWithoutPerson->id)->exists())->toBeTrue();
    expect($circle->members()->whereKey($owner->id)->exists())->toBeFalse();

    expect(Person::where('user_id', $userWithoutPerson->id)->exists())->toBeTrue();

    $personIds = Person::whereIn('user_id', [$userWithPerson->id, $userWithoutPerson->id])->pluck('id');
    expect($circle->persons()->whereKey($personIds)->count())->toBe(2);
});

it('is idempotent and does not duplicate pivots when run twice', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    User::factory()->count(3)->create();

    (new AttachAllUsersToCircle($circle))->handle(app(MemberPersonSyncer::class));
    (new AttachAllUsersToCircle($circle))->handle(app(MemberPersonSyncer::class));

    expect($circle->members()->count())->toBe(3);
    expect($circle->persons()->count())->toBe(3);
});

it('skips users already attached to the circle', function () {
    $owner = User::factory()->create();
    $circle = Circle::factory()->for($owner)->create();
    $existing = User::factory()->create();
    $circle->members()->attach($existing);
    $newcomer = User::factory()->create();

    (new AttachAllUsersToCircle($circle))->handle(app(MemberPersonSyncer::class));

    expect($circle->members()->count())->toBe(2);
    expect($circle->members()->whereKey($newcomer->id)->exists())->toBeTrue();
});
