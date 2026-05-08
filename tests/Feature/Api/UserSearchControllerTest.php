<?php

use App\Models\Circle;
use App\Models\User;

it('requires authentication', function () {
    $this->getJson('/api/users/search?q=ann')
        ->assertUnauthorized();
});

it('returns users that share a circle the auth user owns', function () {
    $auth = User::factory()->create();
    $member = User::factory()->create(['name' => 'Annabelle Stone']);
    $stranger = User::factory()->create(['name' => 'Annabelle Doe']);

    $circle = Circle::factory()->for($auth)->create();
    $circle->members()->attach($member);

    $this->actingAs($auth)
        ->getJson('/api/users/search?q=anna')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $member->id)
        ->assertJsonMissing(['id' => $stranger->id])
        ->assertJsonMissing(['email' => $member->email]);
});

it('returns users that own a circle the auth user is a member of', function () {
    $auth = User::factory()->create();
    $owner = User::factory()->create(['username' => 'jane_doe']);

    $circle = Circle::factory()->for($owner)->create();
    $circle->members()->attach($auth);

    $this->actingAs($auth)
        ->getJson('/api/users/search?q=jane')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $owner->id);
});

it('returns co-members of a circle the auth user is in', function () {
    $auth = User::factory()->create();
    $coMember = User::factory()->create(['email' => 'coworker@example.com']);
    $owner = User::factory()->create();

    $circle = Circle::factory()->for($owner)->create();
    $circle->members()->attach([$auth->id, $coMember->id]);

    $this->actingAs($auth)
        ->getJson('/api/users/search?q=coworker@example.com')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $coMember->id);
});

it('matches by name, username, or email', function () {
    $auth = User::factory()->create();
    $byName = User::factory()->create(['name' => 'Sven Ericsson']);
    $byUsername = User::factory()->create(['username' => 'svenny']);
    $byEmail = User::factory()->create(['email' => 'sven@inbox.test']);

    $circle = Circle::factory()->for($auth)->create();
    $circle->members()->attach([$byName->id, $byUsername->id, $byEmail->id]);

    $this->actingAs($auth)
        ->getJson('/api/users/search?q=sven')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('excludes the authenticated user from results', function () {
    $auth = User::factory()->create(['name' => 'Search Self']);
    Circle::factory()->for($auth)->create();

    $this->actingAs($auth)
        ->getJson('/api/users/search?q=search')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('does not return users from circles the auth user is not in', function () {
    $auth = User::factory()->create();
    $other = User::factory()->create(['name' => 'Outsider Sam']);

    $circle = Circle::factory()->create();
    $circle->members()->attach($other);

    $this->actingAs($auth)
        ->getJson('/api/users/search?q=outsider')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns an empty result when the auth user has no circles', function () {
    $auth = User::factory()->create();

    $this->actingAs($auth)
        ->getJson('/api/users/search?q=anyone')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.last_page', 1);
});

it('returns all shared-circle users (paginated) without a query', function () {
    $auth = User::factory()->create();
    $a = User::factory()->create(['name' => 'Aaron']);
    $b = User::factory()->create(['name' => 'Beatrice']);
    $c = User::factory()->create(['name' => 'Charlie']);

    $circle = Circle::factory()->for($auth)->create();
    $circle->members()->attach([$a->id, $b->id, $c->id]);

    $this->actingAs($auth)
        ->getJson('/api/users/search')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.name', 'Aaron')
        ->assertJsonPath('data.2.name', 'Charlie')
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

it('paginates results when there are more than 30 users in shared circles', function () {
    $auth = User::factory()->create();
    $circle = Circle::factory()->for($auth)->create();

    $members = User::factory()->count(35)->create();
    $circle->members()->attach($members->pluck('id'));

    $this->actingAs($auth)
        ->getJson('/api/users/search?page=1')
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 35);

    $this->actingAs($auth)
        ->getJson('/api/users/search?page=2')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});
