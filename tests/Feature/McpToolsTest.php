<?php

use App\Enums\ScrapeRunStatus;
use App\Jobs\EnrichRunLeads;
use App\Mcp\Servers\OutreachServer;
use App\Mcp\Tools\EnrichLeads;
use App\Mcp\Tools\EnrichRun;
use App\Mcp\Tools\ListLeads;
use App\Mcp\Tools\ScrapeStatus;
use App\Mcp\Tools\SearchLeads;
use App\Models\Lead;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Support\Enrichment\SiteReader;
use App\Support\Places\PlaceResult;
use App\Support\Places\PlacesPage;
use App\Support\Places\PlacesSearch;
use Illuminate\Support\Facades\Queue;

test('search_leads finds businesses, creates the niche and returns the new leads without enriching', function () {
    app(PlacesSearch::class)->shouldReceive('search')->once()->andReturn(new PlacesPage([
        new PlaceResult('p1', 'Tandarts De Linde', null, 'Haarlem', 'https://tandartsdelinde.nl', '023 1'),
        new PlaceResult('p2', 'Mondzorg Zuid', null, 'Haarlem', 'https://mondzorgzuid.nl', null),
    ], null));
    app(SiteReader::class)->shouldNotReceive('fetch');

    $response = OutreachServer::actingAs($this->user)->tool(SearchLeads::class, [
        'query' => 'tandarts', 'place' => 'Haarlem', 'niche' => 'Tandartsen', 'pages' => 1,
    ]);

    $response->assertOk()->assertSee('2 new leads')->assertSee('Tandartsen');

    expect($this->user->niches()->where('name', 'Tandartsen')->exists())->toBeTrue()
        ->and($this->user->leads()->count())->toBe(2)
        ->and($this->user->leads()->first()->signals)->toBeNull()
        ->and($this->user->scrapeRuns()->first()->status)->toBe(ScrapeRunStatus::Done);
});

test('search_leads refuses without a places key', function () {
    $this->user->forceFill(['google_places_key' => null])->save();

    OutreachServer::actingAs($this->user)
        ->tool(SearchLeads::class, ['query' => 'tandarts', 'place' => 'Haarlem', 'niche' => 'Tandartsen'])
        ->assertHasErrors(['This account has no Google Places key yet. Add one under Settings → Google in the app.']);
});

test('search_leads acts as the configured account when nobody is signed in', function () {
    $other = User::factory()->onboarded()->create(['email' => 'mcp@example.com']);
    config()->set('services.outreach.mcp_user', 'mcp@example.com');
    app(PlacesSearch::class)->shouldReceive('search')->once()->andReturn(new PlacesPage([], null));

    auth()->logout();

    OutreachServer::tool(SearchLeads::class, ['query' => 'kapper', 'place' => 'Delft', 'niche' => 'Kappers'])->assertOk();

    expect($other->scrapeRuns()->count())->toBe(1)
        ->and($this->user->scrapeRuns()->count())->toBe(0);
});

test('enrich_leads reads the sites right away for a few and reports what it found', function () {
    $lead = Lead::factory()->create(['website' => 'tandartsdelinde.nl', 'email' => null, 'signals' => null]);
    app(SiteReader::class)->shouldReceive('fetch')->andReturnUsing(fn (string $url) => $url === 'https://tandartsdelinde.nl'
        ? '<html><meta name="viewport" content="x"><body>© 2017 info@tandartsdelinde.nl wp-content</body></html>'
        : null);

    OutreachServer::actingAs($this->user)
        ->tool(EnrichLeads::class, ['lead_ids' => [$lead->id]])
        ->assertOk()
        ->assertSee('1 leads read, 1 with an email address')
        ->assertSee('info@tandartsdelinde.nl');

    expect($lead->fresh()->signals['copyright_year'])->toBe(2017);
});

test('enrich_leads only sees the account\'s own leads', function () {
    $foreign = Lead::factory()->create(['user_id' => User::factory()->create()->id]);

    OutreachServer::actingAs($this->user)
        ->tool(EnrichLeads::class, ['lead_ids' => [$foreign->id]])
        ->assertOk()
        ->assertSee('0 leads read')
        ->assertSee("Not on this account: {$foreign->id}");
});

test('enrich_leads queues bigger sets', function () {
    Queue::fake();
    $leads = Lead::factory()->count(6)->create(['website' => 'x.nl']);

    OutreachServer::actingAs($this->user)
        ->tool(EnrichLeads::class, ['lead_ids' => $leads->modelKeys()])
        ->assertOk()
        ->assertSee('6 leads queued');

    Queue::assertPushed(App\Jobs\EnrichLeads::class);
});

test('enrich_run queues the job for the leads not read yet', function () {
    Queue::fake();
    $run = ScrapeRun::factory()->create();
    Lead::factory()->count(3)->create(['scrape_run_id' => $run->id, 'signals' => null]);
    Lead::factory()->create(['scrape_run_id' => $run->id]);

    OutreachServer::actingAs($this->user)
        ->tool(EnrichRun::class, ['run_id' => $run->id])
        ->assertOk()
        ->assertSee('queued for 3 leads');

    Queue::assertPushed(EnrichRunLeads::class);
});

test('scrape_status sums up a run', function () {
    $run = ScrapeRun::factory()->create(['found' => 40, 'with_email' => 12, 'blocked' => 2]);
    Lead::factory()->count(2)->create(['scrape_run_id' => $run->id]);
    Lead::factory()->create(['scrape_run_id' => $run->id, 'signals' => null]);

    OutreachServer::actingAs($this->user)
        ->tool(ScrapeStatus::class, ['run_id' => $run->id])
        ->assertOk()
        ->assertSee('40 found, 3 leads, 2 enriched, 12 with email, 2 blocked');
});

test('list_leads narrows to status and email', function () {
    Lead::factory()->count(2)->create(['status' => 'new']);
    Lead::factory()->create(['status' => 'replied', 'email' => null]);
    Lead::factory()->create(['status' => 'replied']);

    OutreachServer::actingAs($this->user)
        ->tool(ListLeads::class, ['status' => 'replied', 'with_email' => true])
        ->assertOk()
        ->assertSee('1 leads');
});
