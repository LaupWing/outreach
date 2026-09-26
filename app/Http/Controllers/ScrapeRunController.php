<?php

namespace App\Http\Controllers;

use App\Enums\ScrapeRunStatus;
use App\Http\Requests\ScrapeRuns\StoreScrapeRunRequest;
use App\Jobs\ProcessScrapeRun;
use App\Support\PlacesBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScrapeRunController extends Controller
{
    /**
     * Every run, newest first, with the month's Places budget above it.
     */
    public function index(): Response
    {
        $user = request()->user();

        return Inertia::render('scrape/index', [
            'runs' => $user->scrapeRuns()->orderByDesc('started_at')->orderByDesc('id')->get(),
            'niches' => $user->niches()->orderBy('name')->get(),
            'leads' => $user->leads()
                ->select(['id', 'company', 'email', 'status', 'scrape_run_id', 'website', 'signals'])
                ->whereNotNull('scrape_run_id')
                ->get(),
            'usage' => PlacesBudget::current($user),
        ]);
    }

    /**
     * Queue a Places search.
     *
     * Only the row is written for now: the job that calls Places, reads the sites
     * and fills in the counts runs on the queue; the page polls while it works.
     */
    public function store(StoreScrapeRunRequest $request): RedirectResponse
    {
        $user = $request->user();

        $nicheId = $request->filled('new_niche')
            ? $user->niches()->create(['name' => $request->string('new_niche')->trim()->toString()])->id
            : $request->integer('niche_id');

        $run = $user->scrapeRuns()->create([
            ...$request->safe()->only(['query', 'place', 'pages']),
            'niche_id' => $nicheId,
            'status' => ScrapeRunStatus::Queued,
            'requests' => 0,
            'found' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        ProcessScrapeRun::dispatch($run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scrape queued. Leads show up as the run finishes.')]);

        return back();
    }
}
