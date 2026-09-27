<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\LeadSummary;
use App\Mcp\Resources\LeadListApp;
use App\Models\Lead;
use App\Models\Offer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_leads')]
#[Description('Change one or many leads at once. Per lead: lead_id plus only the fields to change: email, hook, offer_id, status, facts (merged; empty string removes a fact), note (added to the activity). Unknown ids are reported, the rest still goes through.')]
#[IsIdempotent]
#[RendersApp(resource: LeadListApp::class)]
class UpdateLeads extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjectsIn($request->all(), 'leads', ['facts']));

        $validated = $request->validate([
            'leads' => ['required', 'array', 'min:1', 'max:200'],
            'leads.*.lead_id' => ['required', 'integer'],
            'leads.*.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'leads.*.hook' => ['sometimes', 'nullable', 'string', 'max:500'],
            'leads.*.offer_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'leads.*.status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'leads.*.facts' => ['sometimes', 'array'],
            'leads.*.facts.*' => ['nullable', 'string', 'max:2000'],
            'leads.*.note' => ['sometimes', 'string', 'max:5000'],
        ]);

        $updated = [];
        $unknown = [];

        foreach ($validated['leads'] as $row) {
            $lead = $user->leads()->find($row['lead_id']);

            if ($lead === null) {
                $unknown[] = $row['lead_id'];

                continue;
            }

            $updated[] = $this->apply($lead, $row);
        }

        $text = sprintf('%d leads updated.', count($updated));

        if ($unknown !== []) {
            $text .= ' Not on this account: '.implode(', ', $unknown).'.';
        }

        return Response::make(Response::text($text))->withStructuredContent([
            'leads' => array_map(fn (Lead $lead) => [...LeadSummary::from($lead), 'facts' => $lead->facts], $updated),
            'unknown' => $unknown,
            'url' => rtrim(config('app.url'), '/').'/leads',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function apply(Lead $lead, array $row): Lead
    {
        if (array_key_exists('facts', $row)) {
            $facts = [...($lead->facts ?? []), ...$row['facts']];
            $facts = array_filter(array_map(fn ($value) => trim((string) $value), $facts), fn (string $value) => $value !== '');
            $lead->facts = $facts === [] ? null : $facts;
        }

        foreach (['email', 'hook', 'offer_id', 'status'] as $field) {
            if (array_key_exists($field, $row)) {
                $lead->{$field} = $field === 'email' && $row[$field] !== null ? strtolower(trim($row[$field])) : $row[$field];
            }
        }

        $lead->save();

        if (isset($row['note'])) {
            $lead->notes()->create(['user_id' => $lead->user_id, 'body' => $row['note']]);
        }

        return $lead;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leads' => $schema->array()->description('[{lead_id, email?, hook?, offer_id?, status?, facts?, note?}]; only given fields change.')->required(),
        ];
    }
}
