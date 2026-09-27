<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStatus;
use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Models\Offer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_lead')]
#[Description('Change a lead: set or merge facts (the values for {{tags}} in its offer\'s mails, e.g. {"compliment": "…"}), the hook, the offer, the status, or add a note. Only the fields given are touched. Facts merge into what is there; a fact set to an empty string is removed.')]
#[IsIdempotent]
class UpdateLead extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'lead_id' => ['required', 'integer'],
            'facts' => ['sometimes', 'array'],
            'facts.*' => ['nullable', 'string', 'max:2000'],
            'hook' => ['sometimes', 'nullable', 'string', 'max:500'],
            'offer_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'note' => ['sometimes', 'string', 'max:5000'],
        ]);

        $lead = $user->leads()->find($validated['lead_id']);

        if ($lead === null) {
            return Response::error("No lead with id {$validated['lead_id']} on this account.");
        }

        $changed = [];

        if (array_key_exists('facts', $validated)) {
            $facts = [...($lead->facts ?? []), ...$validated['facts']];
            $facts = array_filter(array_map(fn ($value) => trim((string) $value), $facts), fn (string $value) => $value !== '');
            $lead->facts = $facts === [] ? null : $facts;
            $changed[] = 'facts';
        }

        foreach (['hook', 'offer_id', 'status'] as $field) {
            if (array_key_exists($field, $validated)) {
                $lead->{$field} = $validated[$field];
                $changed[] = $field;
            }
        }

        $lead->save();

        if (isset($validated['note'])) {
            $lead->notes()->create(['user_id' => $lead->user_id, 'body' => $validated['note']]);
            $changed[] = 'note';
        }

        return Response::make(Response::text(sprintf('%s updated: %s.', $lead->company, $changed === [] ? 'nothing given' : implode(', ', $changed))))
            ->withStructuredContent([...LeadSummary::from($lead), 'facts' => $lead->facts]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->description('The lead to change.')->required(),
            'facts' => $schema->object()->description('Tag values to merge, e.g. {"compliment": "Mooi dat …", "first_name": "Sanne"}. Empty string removes a fact.'),
            'hook' => $schema->string()->description('One sentence about their site that opens the first mail; also becomes the subject when the sequence uses {{hook_subject}}.'),
            'offer_id' => $schema->integer()->description('The offer (and so the sequence) this lead is worked with.'),
            'status' => $schema->string()->enum(array_map(fn (LeadStatus $status) => $status->value, LeadStatus::cases()))->description('Set by hand, e.g. "no" after a call, "customer" when they signed.'),
            'note' => $schema->string()->description('A note to add to the lead\'s activity, e.g. what was said on the phone.'),
        ];
    }
}
