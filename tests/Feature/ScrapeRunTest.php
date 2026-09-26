<?php

use App\Enums\ScrapeRunStatus;
use App\Models\Lead;
use App\Models\Niche;
use App\Models\ScrapeRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are sent to the login page', function () {
    $this->get(route('scrape.index'))->assertRedirect(route('login'));
});

test('the scrape page lists runs newest first and counts only this month against the budget', function () {
    $this->travelTo(now()->setDay(15));

    $niche = Niche::factory()->create();
    $older = ScrapeRun::factory()->for($niche)->create(['requests' => 3, 'started_at' => now()->subDays(2)]);
    $newest = ScrapeRun::factory()->for($niche)->create(['requests' => 2, 'started_at' => now()->subHour()]);
    ScrapeRun::factory()->for($niche)->create(['requests' => 3, 'started_at' => now()->subMonthNoOverflow()]);
    $lead = Lead::factory()->for($niche)->for($newest)->create();

    $this->actingAs(User::factory()->onboarded()->create())
        ->get(route('scrape.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('scrape/index')
            ->has('runs', 3)
            ->where('runs.0.id', $newest->id)
            ->where('runs.1.id', $older->id)
            ->where('runs.0.status', 'done')
            ->has('niches', 1)
            ->has('leads', 1, fn (AssertableInertia $row) => $row
                ->where('id', $lead->id)
                ->where('scrape_run_id', $newest->id)
                ->hasAll(['company', 'email', 'status', 'website', 'signals']))
            ->where('usage.used', 5)
            ->where('usage.free_limit', 1000)
            ->where('usage.price_per_1000', 35)
            ->where('usage.sku', 'Text Search Enterprise')
            ->where('usage.resets_at', now()->addMonthNoOverflow()->startOfMonth()->toJSON()));
});

test('a scrape is queued for an existing niche', function () {
    $this->freezeTime();
    $niche = Niche::factory()->create();

    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('scrape.store'), [
            'query' => 'tandarts',
            'place' => 'Haarlem',
            'niche_id' => $niche->id,
            'pages' => 3,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $run = ScrapeRun::query()->sole();

    expect($run->niche_id)->toBe($niche->id)
        ->and($run->query)->toBe('tandarts')
        ->and($run->place)->toBe('Haarlem')
        ->and($run->status)->toBe(ScrapeRunStatus::Queued)
        ->and($run->requests)->toBe(0)
        ->and($run->found)->toBe(0)
        ->and($run->started_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($run->finished_at)->toBeNull();
});

test('a scrape can create its niche on the way', function () {
    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('scrape.store'), [
            'query' => 'advocaat',
            'place' => 'Utrecht',
            'new_niche' => 'Advocatenkantoren',
            'pages' => 1,
        ])
        ->assertSessionHasNoErrors();

    $niche = Niche::query()->where('name', 'Advocatenkantoren')->sole();

    expect(ScrapeRun::query()->sole()->niche_id)->toBe($niche->id);
});

test('a scrape needs a niche', function () {
    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('scrape.store'), [
            'query' => 'advocaat',
            'place' => 'Utrecht',
            'pages' => 1,
        ])
        ->assertSessionHasErrors(['niche_id', 'new_niche']);
});

test('a scrape is refused when it would pass the free budget', function () {
    $niche = Niche::factory()->create();
    ScrapeRun::factory()->for($niche)->create(['requests' => 999, 'started_at' => now()]);

    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('scrape.store'), [
            'query' => 'tandarts',
            'place' => 'Haarlem',
            'niche_id' => $niche->id,
            'pages' => 2,
        ])
        ->assertSessionHasErrors('pages');

    expect(ScrapeRun::query()->count())->toBe(1);
});
