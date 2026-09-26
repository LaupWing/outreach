<?php

namespace App\Jobs;

use App\Enums\LeadSource;
use App\Enums\ScrapeRunStatus;
use App\Models\Lead;
use App\Models\ScrapeRun;
use App\Support\Enrichment\Enricher;
use App\Support\Places\PlaceResult;
use App\Support\Places\PlacesException;
use App\Support\Places\PlacesSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Works one queued run: pages through Google Places, turns every business into
 * a lead of the run's owner, reads each site for an email and signals, and
 * writes the counts back as it goes. Runs without a signed-in user, so every
 * query here is scoped through the run by hand.
 */
class ProcessScrapeRun implements ShouldQueue
{
    use Queueable;

    /** Sixty sites at ten seconds each is the worst case. */
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly ScrapeRun $run) {}

    public function handle(PlacesSearch $places, Enricher $enricher): void
    {
        $run = $this->run;
        $apiKey = $run->user->google_places_key;

        if ($apiKey === null) {
            $this->fail('No Google Places key on the account. Add one under Settings → Google.');

            return;
        }

        $run->forceFill(['status' => ScrapeRunStatus::Running, 'error' => null, 'started_at' => now()])->save();

        try {
            $token = null;

            for ($page = 1; $page <= $run->pages; $page++) {
                $result = $places->search($apiKey, $run->query, $run->place, $token);

                $run->increment('requests');
                $run->increment('found', count($result->places));

                foreach ($result->places as $place) {
                    $lead = $this->leadFor($run, $place);

                    if ($lead === null) {
                        continue;
                    }

                    $enricher->enrich($lead);

                    $run->increment('with_email', $lead->email === null ? 0 : 1);
                    $run->increment('blocked', ($lead->signals['blocked'] || $lead->signals['javascript_only']) ? 1 : 0);
                }

                $token = $result->nextPageToken;

                if ($token === null) {
                    break;
                }
            }
        } catch (PlacesException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $run->forceFill(['status' => ScrapeRunStatus::Done, 'finished_at' => now()])->save();
    }

    /**
     * A new lead for the place, or null when this account already has that business.
     */
    private function leadFor(ScrapeRun $run, PlaceResult $place): ?Lead
    {
        $host = $place->host();

        $exists = $run->user->leads()
            ->where(fn ($query) => $query
                ->when($host !== null, fn ($query) => $query->where('website', $host))
                ->orWhere(fn ($query) => $query->where('company', $place->name)->where('city', $place->city)))
            ->exists();

        if ($exists) {
            return null;
        }

        return $run->user->leads()->create([
            'niche_id' => $run->niche_id,
            'scrape_run_id' => $run->id,
            'company' => $place->name,
            'website' => $host,
            'phone' => $place->phone,
            'city' => $place->city,
            'source' => LeadSource::Places,
        ]);
    }

    /**
     * Mark the run failed with a reason the panel can show.
     */
    public function fail(string|Throwable|null $reason = null): void
    {
        $this->run->forceFill([
            'status' => ScrapeRunStatus::Failed,
            'error' => $reason instanceof Throwable ? $reason->getMessage() : $reason,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * The queue calls this when the job itself blows up.
     */
    public function failed(?Throwable $exception): void
    {
        $this->fail($exception?->getMessage() ?? 'The scrape stopped unexpectedly.');
    }
}
