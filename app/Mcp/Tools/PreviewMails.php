<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\MailCard;
use App\Mcp\Reply;
use App\Mcp\Resources\MailCardApp;
use App\Models\Lead;
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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('preview_mails')]
#[Description('Show mails as cards before anything is sent, one or many: per mail either a sequence step with the tags filled (lead_id, step?, values? for custom tags, NOT saved yet) or a subject and body you wrote. Each card has Send and "Edit in Snelreach" buttons. Nothing is queued by this tool.')]
#[IsReadOnly]
#[RendersApp(resource: MailCardApp::class)]
class PreviewMails extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjectsIn($request->all(), 'mails', ['values']));

        $validated = $request->validate([
            'mails' => ['required', 'array', 'min:1', 'max:50'],
            'mails.*.lead_id' => ['required', 'integer'],
            'mails.*.step' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'mails.*.values' => ['sometimes', 'array'],
            'mails.*.values.*' => ['nullable', 'string', 'max:2000'],
            'mails.*.subject' => ['sometimes', 'string', 'max:255'],
            'mails.*.body' => ['required_with:mails.*.subject', 'string', 'max:20000'],
            'offer_id' => ['sometimes', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
        ]);

        $mailbox = $outbox->pick($user)?->address;
        $cards = [];
        $skipped = [];

        foreach ($validated['mails'] as $row) {
            $lead = $user->leads()->find($row['lead_id']);

            if ($lead === null) {
                $skipped[] = "#{$row['lead_id']}: not on this account";

                continue;
            }

            $card = $this->card($user, $lead, $row, $validated['offer_id'] ?? null, $mailbox);

            if (is_string($card)) {
                $skipped[] = "{$lead->company}: {$card}";
            } else {
                $cards[] = $card;
            }
        }

        $text = sprintf('%d previews.', count($cards));

        if ($skipped !== []) {
            $text .= ' Skipped: '.implode('; ', $skipped).'.';
        }

        return Reply::make($text, ['mails' => $cards, 'skipped' => $skipped, 'url' => rtrim(config('app.url'), '/').'/leads']);
    }

    /**
     * The card for one mail, or the reason there is none.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|string
     */
    private function card(User $user, Lead $lead, array $row, ?int $offerId, ?string $mailbox): array|string
    {
        if (isset($row['subject'])) {
            return MailCard::preview($user, $lead, $mailbox, $row['subject'], $row['body'], [], 'send_mails', [
                'mails' => [['lead_id' => $lead->id, 'subject' => $row['subject'], 'body' => $row['body']]],
            ]);
        }

        $offerId ??= $lead->offer_id;

        if ($offerId === null) {
            return 'no offer; pass offer_id, or subject and body';
        }

        // Values are tried on a copy: the preview must not change the lead.
        $values = array_filter(array_map(fn ($value) => trim((string) $value), $row['values'] ?? []), fn (string $value) => $value !== '');
        $lead->facts = [...($lead->facts ?? []), ...$values];

        $lastStep = (int) $lead->messages()->whereIn('status', ['sent', 'replied'])->max('step');
        $number = $row['step'] ?? $lastStep + 1;
        $step = $user->sequenceSteps()->where('offer_id', $offerId)->where('step', $number)->first();

        if ($step === null) {
            return "the offer has no step {$number}";
        }

        $text = $step->subject.' '.$step->body;

        return MailCard::preview($user, $lead, $mailbox, Placeholders::fill($step->subject, $lead), Placeholders::fill($step->body, $lead), Placeholders::missing($text, $lead), 'send_steps', [
            'leads' => [['lead_id' => $lead->id, 'step' => $number, 'values' => $values]], 'offer_id' => $offerId,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mails' => $schema->array()->description('[{lead_id, step?, values?} | {lead_id, subject, body}]')->required(),
            'offer_id' => $schema->integer()->description('Use this offer for leads that have none.'),
        ];
    }
}
