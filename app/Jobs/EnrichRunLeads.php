<?php

namespace App\Jobs;

use App\Models\ScrapeRun;
use App\Support\Enrichment\Enricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads the sites of a run's leads that were not enriched yet. The MCP tool
 * enrich_run queues this and returns at once; scrape_status shows the progress.
 */
class EnrichRunLeads implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly ScrapeRun $run) {}

    public function handle(Enricher $enricher): void
    {
        $this->run->leads()
            ->whereNull('signals')
            ->orderBy('id')
            ->each(function ($lead) use ($enricher): void {
                $enricher->enrich($lead);
                ProcessScrapeRun::count($this->run, $lead);
            });
    }
}
