<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Support\Enrichment\Enricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads the sites of a hand-picked set of leads, off the request.
 */
class EnrichLeads implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    /**
     * @param  list<int>  $leadIds
     */
    public function __construct(public readonly int $userId, public readonly array $leadIds) {}

    public function handle(Enricher $enricher): void
    {
        Lead::query()
            ->where('user_id', $this->userId)
            ->whereIn('id', $this->leadIds)
            ->whereNotNull('website')
            ->each(fn (Lead $lead) => $enricher->enrich($lead));
    }
}
