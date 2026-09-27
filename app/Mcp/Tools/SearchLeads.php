<?php

namespace App\Mcp\Tools;

use App\Enums\NicheStatus;
use App\Enums\ScrapeRunStatus;
use App\Jobs\ProcessScrapeRun;
use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Mcp\Reply;
use App\Mcp\Resources\LeadListApp;
use App\Support\PlacesBudget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('search_leads')]
#[Description('Search Google Places for businesses in a niche and place, and add them as leads. Runs synchronously and returns the new leads without reading their websites; call enrich_leads or enrich_run for email addresses and signals. Each page is 20 businesses and one request against the free monthly budget.')]
#[IsOpenWorld]
#[RendersApp(resource: LeadListApp::class)]
class SearchLeads extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:100'],
            'place' => ['required', 'string', 'max:100'],
            'niche' => ['required', 'string', 'max:100'],
            'pages' => ['sometimes', 'integer', 'min:1', 'max:3'],
        ], [
            'query.required' => 'Give the kind of business to look for, like "tandarts" or "fysiotherapie".',
            'place.required' => 'Give the city or area, like "Haarlem".',
            'niche.required' => 'Give the niche the leads belong to, by name; a new niche is created when it does not exist yet.',
        ]);

        if ($user->google_places_key === null) {
            return Response::error('This account has no Google Places key yet. Add one under Settings → Google in the app.');
        }

        $pages = (int) ($validated['pages'] ?? 1);

        if ($pages > PlacesBudget::left($user)) {
            return Response::error(sprintf('Only %d free Places requests are left this month; ask for fewer pages.', PlacesBudget::left($user)));
        }

        $niche = $user->niches()->firstOrCreate(
            ['name' => trim($validated['niche'])],
            ['status' => NicheStatus::Idea],
        );

        $run = $user->scrapeRuns()->create([
            'niche_id' => $niche->id,
            'query' => $validated['query'],
            'place' => $validated['place'],
            'pages' => $pages,
            'status' => ScrapeRunStatus::Queued,
            'started_at' => now(),
        ]);

        // Synchronous: Claude is waiting for the answer, and a search is only seconds.
        ProcessScrapeRun::dispatchSync($run, enrich: false);

        $run->refresh();

        if ($run->status === ScrapeRunStatus::Failed) {
            return Response::error($run->error ?? 'The search failed.');
        }

        $leads = $run->leads()->orderBy('id')->get();

        return Reply::make(sprintf(
            '%s in %s: %d businesses found, %d new leads added to niche "%s" (run %d, %d request%s used). None of them are enriched yet.',
            $run->query, $run->place, $run->found, $leads->count(), $niche->name, $run->id, $run->requests, $run->requests === 1 ? '' : 's',
        ), [
            'run_id' => $run->id,
            'run_url' => rtrim(config('app.url'), '/').'/scrape?run='.$run->id,
            'niche_id' => $niche->id,
            'found' => $run->found,
            'requests' => $run->requests,
            'leads' => $leads->map(fn ($lead) => LeadSummary::from($lead))->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The kind of business, as you would type it in Google Maps: "tandarts", "fysiotherapie".')->required(),
            'place' => $schema->string()->description('City or area: "Haarlem", "Amsterdam Noord".')->required(),
            'niche' => $schema->string()->description('Niche name the leads belong to, e.g. "Tandartsen". Created when new.')->required(),
            'pages' => $schema->integer()->description('Pages of 20 businesses, 1 to 3. Each page costs one request of the free monthly 1,000.')->default(1),
        ];
    }
}
