<?php

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard shows the numbers home is built from', function () {
    $this->seed(DemoSeeder::class);
    Lead::query()->where('company', 'Tandartspraktijk De Linde')->update(['next_action_at' => now()->subHour()]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('leads', 12)
            ->has('messages', 10)
            ->has('mailboxes', 3)
            ->where('due', 1)
            ->where('usage.free_limit', 1000)
        );
});

test('the messages page lists every mail newest first', function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/index')
            ->has('messages', 10)
            ->where('messages.0.subject', 'Online afspraken')
            ->has('leads', 12)
            ->has('mailboxes', 3)
        );
});

test('search matches every word across the tables and caps each group', function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs(User::factory()->create())
        ->getJson(route('search', ['q' => 'tandarts haarlem']))
        ->assertOk()
        ->assertJsonPath('runs.0.place', 'Haarlem')
        ->assertJsonPath('leads.0.company', 'Tandarts Bos & Partners')
        ->assertJsonCount(0, 'mailboxes');
});

test('search with an empty query returns nothing', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('search', ['q' => '  ']))
        ->assertOk()
        ->assertExactJson(['leads' => [], 'niches' => [], 'offers' => [], 'mailboxes' => [], 'messages' => [], 'runs' => []]);
});
