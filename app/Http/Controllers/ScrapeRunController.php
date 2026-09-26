<?php

namespace App\Http\Controllers;

use App\Enums\ScrapeRunStatus;
use App\Http\Requests\ScrapeRuns\StoreScrapeRunRequest;
use App\Models\Lead;
use App\Models\Niche;
use App\Models\ScrapeRun;
use App\Support\PlacesBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScrapeRunController extends Controller
{
    /**
     * Every run, newest first, with the month's Places budget above it.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', ScrapeRun::class);

        return Inertia::render('scrape/index', [
            'runs' => ScrapeRun::query()->orderByDesc('started_at')->orderByDesc('id')->get(),
            'niches' => Niche::query()->orderBy('name')->get(),
            'leads' => Lead::query()
                ->select(['id', 'company', 'email', 'status', 'scrape_run_id', 'website', 'signals'])
                ->whereNotNull('scrape_run_id')
                ->get(),
            'usage' => PlacesBudget::current(),
        ]);
    }

    /**
     * Queue a Places search.
     *
     * Only the row is written for now: the job that calls Places, reads the sites
     * and fills in the counts comes with the scraper. Until then a run stays queued.
     */
    public function store(StoreScrapeRunRequest $request): RedirectResponse
    {
        $nicheId = $request->filled('new_niche')
            ? Niche::query()->create(['name' => $request->string('new_niche')->trim()->toString()])->id
            : $request->integer('niche_id');

        ScrapeRun::query()->create([
            ...$request->safe()->only(['query', 'place']),
            'niche_id' => $nicheId,
            'status' => ScrapeRunStatus::Queued,
            'requests' => 0,
            'found' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scrape queued.')]);

        return back();
    }
}
