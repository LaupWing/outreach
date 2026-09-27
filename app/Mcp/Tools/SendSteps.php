<?php

namespace App\Mcp\Tools;

use App\Enums\MessageStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\LeadSummary;
use App\Mcp\MailCard;
use App\Mcp\Reply;
use App\Mcp\Resources\LeadListApp;
use App\Mcp\Sends;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
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

#[Name('send_steps')]
#[Description('Send a sequence step to one or many leads: per lead the app fills the {{tags}} from its fields and facts and puts the mail in the outbox, which sends at the next free moments inside the sending hours. Per lead: lead_id, optional step (default: the next one), optional values for its custom tags (saved as facts). A lead is refused while any tag of this step OR a later step is unfilled (the follow-ups go out by themselves, so fill every step now); leads without email or offer are skipped too. Skipped leads are reported; the rest still goes. With draft=true everything is saved as drafts instead.')]
#[RendersApp(resource: LeadListApp::class)]
class SendSteps extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjectsIn($request->all(), 'leads', ['values']));

        $validated = $request->validate([
            'leads' => ['required', 'array', 'min:1', 'max:200'],
            'leads.*.lead_id' => ['required', 'integer'],
            'leads.*.step' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'leads.*.values' => ['sometimes', 'array'],
            'leads.*.values.*' => ['nullable', 'string', 'max:2000'],
            'offer_id' => ['sometimes', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'mailbox_id' => ['sometimes', 'integer'],
            'draft' => ['sometimes', 'boolean'],
        ]);

        $queued = [];
        $cards = [];
        $problems = [];

        foreach ($validated['leads'] as $row) {
            $lead = $user->leads()->find($row['lead_id']);

            if ($lead === null) {
                $problems[] = "#{$row['lead_id']}: not on this account";

                continue;
            }

            $result = $this->queue($outbox, $user, $lead, $row, $validated);

            if (is_string($result)) {
                $problems[] = "{$lead->company}: {$result}";
            } else {
                $queued[] = $lead;
                $cards[] = MailCard::message($user, $result->load(['lead', 'mailbox']));
            }
        }

        $text = sprintf('%d mails %s.', count($queued), ($validated['draft'] ?? false) ? 'saved as drafts' : 'queued');

        if ($problems !== []) {
            $text .= ' Skipped: '.implode('; ', $problems).'.';
        }

        return Reply::make($text, [
            'leads' => array_map(fn (Lead $lead) => [...LeadSummary::from($lead->refresh()), 'facts' => $lead->facts], $queued),
            'skipped' => $problems,
            'url' => rtrim(config('app.url'), '/').'/messages',
        ]);
    }

    /**
     * Queue one lead's step: the message, or the reason it could not go.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $shared
     */
    private function queue(Outbox $outbox, User $user, Lead $lead, array $row, array $shared): Message|string
    {
        if ($lead->email === null) {
            return 'no email address';
        }

        if (isset($shared['offer_id']) && $lead->offer_id === null) {
            $lead->offer_id = $shared['offer_id'];
        }

        if ($lead->offer_id === null) {
            return 'no offer; pass offer_id';
        }

        if (isset($row['values'])) {
            $facts = [...($lead->facts ?? []), ...$row['values']];
            $facts = array_filter(array_map(fn ($value) => trim((string) $value), $facts), fn (string $value) => $value !== '');
            $lead->facts = $facts === [] ? null : $facts;
        }

        $lead->save();

        $lastStep = (int) $lead->messages()->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied])->max('step');
        $number = $row['step'] ?? $lastStep + 1;
        $step = $user->sequenceSteps()->where('offer_id', $lead->offer_id)->where('step', $number)->first();

        if ($step === null) {
            return "the offer has no step {$number}";
        }

        // This step and every step after it: the follow-ups go out by themselves later,
        // so their tags have to be filled now, or they would stall on the day they are due.
        $remaining = $user->sequenceSteps()->where('offer_id', $lead->offer_id)->where('step', '>=', $number)->get();
        $text = $remaining->map(fn ($later) => $later->subject.' '.$later->body)->implode(' ');
        $missing = Placeholders::missing($text, $lead);

        if ($missing !== []) {
            return 'unfilled {{'.implode('}}, {{', $missing).'}} (in this step or a follow-up); pass them in values';
        }

        $mailbox = $outbox->pick($user, $shared['mailbox_id'] ?? null);

        if ($mailbox === null) {
            return 'every mailbox is full for today';
        }

        $attributes = [
            'subject' => Placeholders::fill($step->subject, $lead),
            'body' => Placeholders::fill($step->body, $lead),
            'step' => $step->step,
            'thread_id' => $number > 1 ? Sends::threadOf($lead) : null,
        ];

        return Sends::queue($outbox, $lead, $mailbox, $attributes, $shared['draft'] ?? false);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leads' => $schema->array()->description('[{lead_id, step?, values?}]. values: custom tag values for that lead, e.g. {"compliment": "…"}.')->required(),
            'offer_id' => $schema->integer()->description('Assign this offer to leads that have none.'),
            'mailbox_id' => $schema->integer()->description('Send from this mailbox. Default: the box with the most room.'),
            'draft' => $schema->boolean()->description('Save as drafts instead of queueing.')->default(false),
        ];
    }
}
