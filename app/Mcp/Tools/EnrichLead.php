<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Mcp\Resources\LeadCardApp;
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

#[Name('enrich_lead')]
#[Description('Read one lead\'s website for an email address and the signals a hook is built from: copyright year, mobile viewport, software (WordPress, Wix, …) and the newest dated content. Sites that block us or render with JavaScript are flagged, not dropped. Takes a few seconds.')]
#[IsOpenWorld]
#[IsIdempotent]
#[RendersApp(resource: LeadCardApp::class)]
class EnrichLead extends Tool
{
    public function handle(Request $request, Enricher $enricher): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate(['lead_id' => ['required', 'integer']]);

        $lead = $user->leads()->find($validated['lead_id']);

        if ($lead === null) {
            return Response::error("No lead with id {$validated['lead_id']} on this account.");
        }

        if ($lead->website === null) {
            return Response::error("Lead {$lead->id} ({$lead->company}) has no website to read.");
        }

        $enricher->enrich($lead);

        $signals = $lead->signals;
        $note = match (true) {
            $signals['blocked'] => 'The site blocked the request; check it by hand.',
            $signals['javascript_only'] => 'The site renders with JavaScript; only the shell was read.',
            $lead->email === null => 'No email address found on the site.',
            default => "Email found: {$lead->email}.",
        };

        return Response::make(Response::text("{$lead->company} ({$lead->website}): {$note}"))
            ->withStructuredContent(['lead' => LeadSummary::from($lead)]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->description('The lead to enrich.')->required(),
        ];
    }
}
