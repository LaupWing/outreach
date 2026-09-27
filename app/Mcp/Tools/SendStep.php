<?php

namespace App\Mcp\Tools;

use App\Enums\MessageStatus;
use App\Mcp\Account;
use App\Mcp\Resources\MailCardApp;
use App\Mcp\Sends;
use App\Models\Offer;
use App\Support\Mail\Outbox;
use App\Support\Mail\Placeholders;
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

#[Name('send_step')]
#[Description('Send a step of the lead\'s offer sequence: the app fills the {{tags}} from the lead\'s fields and facts and puts the mail in the outbox, which sends it at the next free moment inside the account\'s sending hours. Pass values for the custom tags (they are saved as facts). Refuses when a tag is still unfilled. With draft=true the mail is saved as a draft instead, to send later with send_draft.')]
#[RendersApp(resource: MailCardApp::class)]
class SendStep extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'lead_id' => ['required', 'integer'],
            'step' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'values' => ['sometimes', 'array'],
            'values.*' => ['nullable', 'string', 'max:2000'],
            'offer_id' => ['sometimes', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'mailbox_id' => ['sometimes', 'integer'],
            'draft' => ['sometimes', 'boolean'],
        ]);

        $lead = $user->leads()->find($validated['lead_id']);

        if ($lead === null) {
            return Response::error("No lead with id {$validated['lead_id']} on this account.");
        }

        if ($lead->email === null) {
            return Response::error("{$lead->company} has no email address; enrich_lead or update it first.");
        }

        if (isset($validated['offer_id'])) {
            $lead->offer_id = $validated['offer_id'];
        }

        if ($lead->offer_id === null) {
            return Response::error("{$lead->company} has no offer; pass offer_id, or use send for a free-form mail.");
        }

        if (isset($validated['values'])) {
            $facts = [...($lead->facts ?? []), ...$validated['values']];
            $facts = array_filter(array_map(fn ($value) => trim((string) $value), $facts), fn (string $value) => $value !== '');
            $lead->facts = $facts === [] ? null : $facts;
        }

        $lead->save();

        $lastStep = (int) $lead->messages()->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied])->max('step');
        $number = $validated['step'] ?? $lastStep + 1;

        $step = $user->sequenceSteps()->where('offer_id', $lead->offer_id)->where('step', $number)->first();

        if ($step === null) {
            return Response::error("The offer has no step {$number}; the sequence has ".$user->sequenceSteps()->where('offer_id', $lead->offer_id)->count().' steps.');
        }

        $missing = Placeholders::missing($step->subject.' '.$step->body, $lead);

        if ($missing !== []) {
            return Response::error('Still unfilled: {{'.implode('}}, {{', $missing).'}}. Pass them in values (see the offer\'s tag_explanations in lead_context).');
        }

        return Sends::queue($outbox, $user, $lead, [
            'subject' => Placeholders::fill($step->subject, $lead),
            'body' => Placeholders::fill($step->body, $lead),
            'step' => $step->step,
            'thread_id' => $number > 1 ? Sends::threadOf($lead) : null,
        ], $validated['mailbox_id'] ?? null, $validated['draft'] ?? false);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->description('The lead to mail.')->required(),
            'step' => $schema->integer()->description('Which step of the sequence. Default: the one after the last step sent.'),
            'values' => $schema->object()->description('Values for the custom tags in that step, e.g. {"compliment": "…"}. Saved on the lead as facts.'),
            'offer_id' => $schema->integer()->description('Assign this offer to the lead first, when it has none.'),
            'mailbox_id' => $schema->integer()->description('Send from this mailbox. Default: the box with the most room today.'),
            'draft' => $schema->boolean()->description('Save as a draft in the app instead of queueing it.')->default(false),
        ];
    }
}
