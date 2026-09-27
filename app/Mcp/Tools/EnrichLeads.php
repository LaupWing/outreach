<?php

namespace App\Mcp\Tools;

use App\Jobs\EnrichLeads as EnrichLeadsJob;
use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Mcp\Reply;
use App\Mcp\Resources\LeadListApp;
use App\Support\Enrichment\Enricher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('enrich_leads')]
#[Description('Read the websites of the given leads for an email address and the signals a hook is built from (copyright year, mobile viewport, software, newest dated content). Up to 5 leads are read right away and returned; more are queued and you get them back with list_leads a minute later. Sites that block us or render with JavaScript are flagged (and rendered in a browser when the server has one). Use enrich_run for a whole scrape run.')]
#[IsOpenWorld]
#[IsIdempotent]
#[RendersApp(resource: LeadListApp::class)]
class EnrichLeads extends Tool
{
    private const RIGHT_AWAY = 5;

    public function handle(Request $request, Enricher $enricher): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1', 'max:200'],
            'lead_ids.*' => ['integer'],
        ]);

        $leads = $user->leads()->whereIn('id', $validated['lead_ids'])->get();
        $unknown = array_values(array_diff($validated['lead_ids'], $leads->modelKeys()));
        $noSite = $leads->whereNull('website');
        $readable = $leads->whereNotNull('website')->values();

        if ($readable->count() > self::RIGHT_AWAY) {
            EnrichLeadsJob::dispatch($user->id, $readable->modelKeys());

            return Reply::make(sprintf(
                '%d leads queued for enrichment; check list_leads in a minute or two.%s%s',
                $readable->count(),
                $noSite->isEmpty() ? '' : ' No website: '.$noSite->pluck('company')->implode(', ').'.',
                $unknown === [] ? '' : ' Not on this account: '.implode(', ', $unknown).'.',
            ), ['queued' => $readable->count(), 'leads' => [], 'url' => rtrim(config('app.url'), '/').'/leads']);
        }

        $done = $readable->map(fn ($lead) => $enricher->enrich($lead));

        $found = $done->filter(fn ($lead) => $lead->email !== null)->count();

        return Reply::make(sprintf(
            '%d leads read, %d with an email address.%s%s',
            $done->count(), $found,
            $noSite->isEmpty() ? '' : ' No website: '.$noSite->pluck('company')->implode(', ').'.',
            $unknown === [] ? '' : ' Not on this account: '.implode(', ', $unknown).'.',
        ), [
            'leads' => $done->map(fn ($lead) => LeadSummary::from($lead))->all(),
            'url' => rtrim(config('app.url'), '/').'/leads',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_ids' => $schema->array()->description('The leads to read, up to 200.')->required(),
        ];
    }
}
