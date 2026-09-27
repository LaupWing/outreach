<?php

use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    auth()->logout();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard shows the numbers home is built from', function () {
    // The demo data has fixed follow-up dates; pin the clock so only the one we set is due.
    Carbon::setTestNow('2026-09-25 12:00:00');
    $this->seed(DemoSeeder::class);
    Lead::query()->where('company', 'Tandartspraktijk De Linde')->update(['next_action_at' => now()->subHour()]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('leads', 12)
            ->has('messages', 10)
            ->has('mailboxes', 1)
            ->where('due', 1)
            ->where('usage.free_limit', 1000)
        );
});

test('the messages page lists every mail newest first', function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs($this->user)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/index')
            ->has('messages.data', 10)
            ->where('messages.data.0.subject', 'Online afspraken')
            ->where('messages.data.0.lead.company', 'Tandarts Bos & Partners')
            ->where('counts.total', 10)
            ->where('linked', null)
            ->has('mailboxes', 1)
        );
});

test('the messages page narrows the rows and the counts to the status filter', function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs($this->user)
        ->get(route('messages.index', ['status' => ['bounced']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/index')
            ->has('messages.data', 1)
            ->where('messages.data.0.status', 'bounced')
            ->where('counts.total', 1)
            ->where('counts.bounced', 1)
            ->where('counts.replied', 0)
        );
});

test('the messages page scrolls the next page in', function () {
    Message::factory()->count(60)->for(Lead::factory())->for(Mailbox::factory())->create();

    $this->actingAs($this->user)
        ->get(route('messages.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/index')
            ->has('messages.data', 10)
            ->where('counts.total', 60)
        );
});

test('search matches every word across the tables and caps each group', function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'tandarts haarlem']))
        ->assertOk()
        ->assertJsonPath('runs.0.place', 'Haarlem')
        ->assertJsonPath('leads.0.company', 'Tandarts Bos & Partners')
        ->assertJsonCount(0, 'mailboxes');
});

test('search with an empty query returns nothing', function () {
    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => '  ']))
        ->assertOk()
        ->assertExactJson(['leads' => [], 'niches' => [], 'offers' => [], 'mailboxes' => [], 'messages' => [], 'runs' => []]);
});
