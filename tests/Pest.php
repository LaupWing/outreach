<?php

use App\Models\User;
use App\Support\MailboxConnection;
use App\Support\Places\PlacesPage;
use App\Support\Places\PlacesSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Rows belong to whoever is signed in, so every test starts as an onboarded owner;
        // guest tests log out first, tenancy tests switch accounts.
        // Inertia responses render the root view; the built assets are not part of the test.
        $this->withoutVite();

        // Nothing talks to Google or a mail server in a test unless a test says otherwise.
        $this->instance(PlacesSearch::class, tap(Mockery::mock(PlacesSearch::class), function ($fake): void {
            $fake->shouldReceive('check')->andReturn(null)->byDefault();
            $fake->shouldReceive('search')->andReturn(new PlacesPage([], null))->byDefault();
        }));
        $this->instance(MailboxConnection::class, tap(Mockery::mock(MailboxConnection::class), function ($fake): void {
            $fake->shouldReceive('check')->andReturn(null)->byDefault();
        }));

        $this->user = User::factory()->onboarded()->create();
        $this->actingAs($this->user);
    })
    ->in('Feature');

// The auth and settings flows test the sign-in itself, so they start as a guest.
pest()->beforeEach(fn () => auth()->logout())->in('Feature/Auth', 'Feature/Settings');

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
