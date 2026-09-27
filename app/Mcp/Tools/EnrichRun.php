<?php

namespace App\Mcp\Tools;

use App\Jobs\EnrichRunLeads;
use App\Mcp\Account;
use App\Mcp\Reply;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('enrich_run')]
#[Description('Queue the enrichment of every lead of a scrape run that has not been read yet. Returns at once; call scrape_status to see how far it is. Use this after search_leads when there are many leads; use enrich_lead for one.')]
#[IsOpenWorld]
#[IsIdempotent]
class EnrichRun extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate(['run_id' => ['required', 'integer']]);

        $run = $user->scrapeRuns()->find($validated['run_id']);

        if ($run === null) {
            return Response::error("No scrape run with id {$validated['run_id']} on this account.");
        }

        $pending = $run->leads()->whereNull('signals')->count();

        if ($pending === 0) {
            return Response::text("Run {$run->id}: every lead is already enriched.");
        }

        EnrichRunLeads::dispatch($run);

        return Reply::make("Run {$run->id}: enrichment queued for {$pending} leads. Check scrape_status in a minute or two.", ['run_id' => $run->id, 'pending' => $pending]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->integer()->description('The scrape run whose leads to enrich.')->required(),
        ];
    }
}
