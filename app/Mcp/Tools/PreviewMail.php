<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\MailCard;
use App\Mcp\Resources\MailCardApp;
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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('preview_mail')]
#[Description('Show a mail as a card before anything is sent: a sequence step with the tags filled (pass values for custom ones, they are NOT saved yet), or a subject and body you wrote. The card has Send and "Edit in Snelreach" buttons. Nothing is queued by this tool.')]
#[IsReadOnly]
#[RendersApp(resource: MailCardApp::class)]
class PreviewMail extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjects($request->all(), ['values']));

        $validated = $request->validate([
            'lead_id' => ['required', 'integer'],
            'step' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'values' => ['sometimes', 'array'],
            'values.*' => ['nullable', 'string', 'max:2000'],
            'offer_id' => ['sometimes', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'subject' => ['required_without_all:step,offer_id', 'sometimes', 'string', 'max:255'],
            'body' => ['required_with:subject', 'sometimes', 'string', 'max:20000'],
        ]);

        $lead = $user->leads()->find($validated['lead_id']);

        if ($lead === null) {
            return Response::error("No lead with id {$validated['lead_id']} on this account.");
        }

        $mailbox = $outbox->pick($user)?->address;

        if (isset($validated['subject'])) {
            $card = MailCard::preview($user, $lead, $mailbox, $validated['subject'], $validated['body'], [], 'send', [
                'lead_id' => $lead->id, 'subject' => $validated['subject'], 'body' => $validated['body'],
            ]);

            return Response::make(Response::text("Preview for {$lead->company}: \"{$validated['subject']}\"."))->withStructuredContent($card);
        }

        $offerId = $validated['offer_id'] ?? $lead->offer_id;

        if ($offerId === null) {
            return Response::error("{$lead->company} has no offer; pass offer_id, or subject and body.");
        }

        // Values are tried on a copy: the preview must not change the lead.
        $values = array_filter(array_map(fn ($value) => trim((string) $value), $validated['values'] ?? []), fn (string $value) => $value !== '');
        $lead->facts = [...($lead->facts ?? []), ...$values];

        $lastStep = (int) $lead->messages()->whereIn('status', ['sent', 'replied'])->max('step');
        $number = $validated['step'] ?? $lastStep + 1;
        $step = $user->sequenceSteps()->where('offer_id', $offerId)->where('step', $number)->first();

        if ($step === null) {
            return Response::error("The offer has no step {$number}.");
        }

        $text = $step->subject.' '.$step->body;
        $card = MailCard::preview($user, $lead, $mailbox, Placeholders::fill($step->subject, $lead), Placeholders::fill($step->body, $lead), Placeholders::missing($text, $lead), 'send_step', [
            'lead_id' => $lead->id, 'step' => $number, 'values' => $values, 'offer_id' => $offerId,
        ]);

        return Response::make(Response::text(sprintf('Preview of step %d for %s%s.', $number, $lead->company, $card['missing_tags'] === [] ? '' : '; unfilled: {{'.implode('}}, {{', $card['missing_tags']).'}}')))
            ->withStructuredContent($card);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->description('The lead the mail is for.')->required(),
            'step' => $schema->integer()->description('Preview this step of the offer sequence. Default: the next one.'),
            'values' => $schema->object()->description('Values for custom tags, tried but not saved.'),
            'offer_id' => $schema->integer()->description('Use this offer when the lead has none.'),
            'subject' => $schema->string()->description('A subject you wrote (with body), instead of a sequence step.'),
            'body' => $schema->string()->description('The body that goes with subject.'),
        ];
    }
}
