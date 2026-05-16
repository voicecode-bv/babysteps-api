<?php

use App\Models\Circle;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Koppelt een nieuwe circle aan een post en zet de opgegeven users als members
 * (via circle_user pivot). De circle-owner is een wegwerp-User die geen rol
 * speelt in de test.
 */
function shareCircle(Post $post, User ...$users): Circle
{
    $circle = Circle::factory()->create();
    $post->circles()->attach($circle);
    $circle->members()->attach(collect($users)->pluck('id')->all());

    return $circle;
}

/**
 * Koppelt een nieuwe circle aan een post met $owner als owner
 * (circles.user_id) en de overige $members via circle_user pivot. Bedoeld om
 * het productie-model te repliceren waar de circle-aanmaker NIET in
 * circle_user pivot zit.
 */
function shareCircleOwnedBy(Post $post, User $owner, User ...$members): Circle
{
    $circle = Circle::factory()->create(['user_id' => $owner->id]);
    $post->circles()->attach($circle);

    if ($members !== []) {
        $circle->members()->attach(collect($members)->pluck('id')->all());
    }

    return $circle;
}
