<?php

use App\Enums\LeadSource;
use App\Enums\ScrapeRunStatus;
use App\Jobs\ProcessScrapeRun;
use App\Models\Lead;
use App\Models\ScrapeRun;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\SiteReader;
use App\Support\Places\PlaceResult;
use App\Support\Places\PlacesException;
use App\Support\Places\PlacesPage;
use App\Support\Places\PlacesSearch;

/** A Places fake that serves the given pages in order. */
function fakePlaces(PlacesPage ...$pages): void
{
    $queue = $pages;

    $fake = Mockery::mock(PlacesSearch::class);
    $fake->shouldReceive('search')->andReturnUsing(function () use (&$queue) {
        return array_shift($queue) ?? new PlacesPage([], null);
    });

    app()->instance(PlacesSearch::class, $fake);
}

/** A site reader that answers from a map of url => html (null = blocked). */
function fakeSites(array $pages): void
{
    $fake = Mockery::mock(SiteReader::class);
    $fake->shouldReceive('fetch')->andReturnUsing(fn (string $url) => $pages[$url] ?? null);

    app()->instance(SiteReader::class, $fake);
}

function place(string $name, ?string $website, string $city = 'Haarlem'): PlaceResult
{
    return new PlaceResult(placeId: 'p-'.md5($name), name: $name, address: null, city: $city, website: $website, phone: '023 111 2222');
}

test('a run turns places into enriched leads of its owner and finishes done', function () {
    $run = ScrapeRun::factory()->create(['status' => ScrapeRunStatus::Queued, 'pages' => 2, 'requests' => 0, 'found' => 0, 'with_email' => 0, 'blocked' => 0]);

    fakePlaces(
        new PlacesPage([place('Tandarts De Linde', 'https://www.tandartsdelinde.nl/'), place('Mondzorg Zuid', 'https://mondzorgzuid.nl')], 'page-2'),
        new PlacesPage([place('Kliniek Noord', 'https://kliniek-noord.nl')], null),
    );

    fakeSites([
        'https://tandartsdelinde.nl' => '<html><meta name="viewport" content="width=device-width"><body>© 2017 <a href="mailto:Info@TandartsDeLinde.nl">mail</a> wp-content</body></html>',
        'https://mondzorgzuid.nl' => '<html><body><time datetime="2021-03-12">news</time> Joomla</body></html>',
        'https://mondzorgzuid.nl/contact' => '<html><body>praktijk@mondzorgzuid.nl</body></html>',
        // kliniek-noord.nl answers nothing: blocked.
    ]);

    (new ProcessScrapeRun($run))->handle(app(PlacesSearch::class), app(Enricher::class));

    $run->refresh();

    expect($run->status)->toBe(ScrapeRunStatus::Done)
        ->and($run->requests)->toBe(2)
        ->and($run->found)->toBe(3)
        ->and($run->with_email)->toBe(2)
        ->and($run->blocked)->toBe(1)
        ->and($run->finished_at)->not->toBeNull();

    $linde = Lead::query()->where('website', 'tandartsdelinde.nl')->sole();

    expect($linde->user_id)->toBe($run->user_id)
        ->and($linde->niche_id)->toBe($run->niche_id)
        ->and($linde->scrape_run_id)->toBe($run->id)
        ->and($linde->source)->toBe(LeadSource::Places)
        ->and($linde->email)->toBe('info@tandartsdelinde.nl')
        ->and($linde->signals['copyright_year'])->toBe(2017)
        ->and($linde->signals['viewport'])->toBeTrue()
        ->and($linde->signals['software'])->toBe(['WordPress']);

    $zuid = Lead::query()->where('website', 'mondzorgzuid.nl')->sole();

    expect($zuid->email)->toBe('praktijk@mondzorgzuid.nl')
        ->and($zuid->signals['last_news_at'])->toBe('2021-03-12')
        ->and($zuid->signals['software'])->toBe(['Joomla']);

    expect(Lead::query()->where('website', 'kliniek-noord.nl')->sole()->signals['blocked'])->toBeTrue();
});

test('a business the account already has is not added twice', function () {
    $run = ScrapeRun::factory()->create(['pages' => 1, 'found' => 0]);
    Lead::factory()->create(['website' => 'tandartsdelinde.nl']);

    fakePlaces(new PlacesPage([place('Tandarts De Linde', 'https://tandartsdelinde.nl')], null));
    fakeSites([]);

    (new ProcessScrapeRun($run))->handle(app(PlacesSearch::class), app(Enricher::class));

    expect(Lead::query()->where('website', 'tandartsdelinde.nl')->count())->toBe(1)
        ->and($run->fresh()->found)->toBe(1);
});

test('a refused google call marks the run failed with the reason', function () {
    $run = ScrapeRun::factory()->create(['status' => ScrapeRunStatus::Queued, 'pages' => 3, 'requests' => 0]);

    $fake = Mockery::mock(PlacesSearch::class);
    $fake->shouldReceive('search')->once()->andThrow(new PlacesException('Google Places refused the search (403): API key not valid'));
    app()->instance(PlacesSearch::class, $fake);
    fakeSites([]);

    (new ProcessScrapeRun($run))->handle(app(PlacesSearch::class), app(Enricher::class));

    $run->refresh();

    expect($run->status)->toBe(ScrapeRunStatus::Failed)
        ->and($run->error)->toContain('API key not valid')
        ->and($run->requests)->toBe(0);
});

test('a run stops early when the account has no places key', function () {
    $run = ScrapeRun::factory()->create();
    $run->user->forceFill(['google_places_key' => null])->save();

    $fake = Mockery::mock(PlacesSearch::class);
    $fake->shouldNotReceive('search');
    app()->instance(PlacesSearch::class, $fake);
    fakeSites([]);

    (new ProcessScrapeRun($run))->handle(app(PlacesSearch::class), app(Enricher::class));

    expect($run->fresh()->status)->toBe(ScrapeRunStatus::Failed)
        ->and($run->fresh()->error)->toContain('Settings');
});
