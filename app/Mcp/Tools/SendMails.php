<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\MailCard;
use App\Mcp\Reply;
use App\Mcp\Resources\MailCardApp;
use App\Mcp\Sends;
use App\Support\Mail\Outbox;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;

#[Name('send_mails')]
#[Description('Send mails you wrote yourself, one or many: per mail lead_id, subject and body as they should go out (plain text). A subject starting with "Re:" continues the lead\'s thread. Pass step to count it as that step of the sequence; without it the mail is a one-off. With draft=true they are saved as drafts instead (send_drafts releases them). Leads without an email address are skipped and reported.')]
#[RendersApp(resource: MailCardApp::class)]
class SendMails extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjects($request->all(), ['mails']));

        $validated = $request->validate([
            'mails' => ['required', 'array', 'min:1', 'max:200'],
            'mails.*.lead_id' => ['required', 'integer'],
            'mails.*.subject' => ['required', 'string', 'max:255'],
            'mails.*.body' => ['required', 'string', 'max:20000'],
            'mails.*.step' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'mailbox_id' => ['sometimes', 'integer'],
            'draft' => ['sometimes', 'boolean'],
        ]);

        $cards = [];
        $skipped = [];

        foreach ($validated['mails'] as $row) {
            $lead = $user->leads()->find($row['lead_id']);

            if ($lead === null) {
                $skipped[] = "#{$row['lead_id']}: not on this account";

                continue;
            }

            if ($lead->email === null) {
                $skipped[] = "{$lead->company}: no email address";

                continue;
            }

            $mailbox = $outbox->pick($user, $validated['mailbox_id'] ?? null);

            if ($mailbox === null) {
                $skipped[] = "{$lead->company}: every mailbox is full for today";

                continue;
            }

            $continues = preg_match('/^re:/i', $row['subject']) === 1 || ($row['step'] ?? 1) > 1;
            // Answering what they wrote back: a reply in the conversation, not a sequence step.
            $answered = $continues && ! isset($row['step']) ? Sends::repliedTo($lead) : null;

            $message = Sends::queue($outbox, $lead, $mailbox, [
                'subject' => $row['subject'],
                'body' => $row['body'],
                'step' => $answered?->step ?? $row['step'] ?? 0,
                'is_reply' => $answered !== null,
                'thread_id' => $answered?->thread_id ?? ($continues ? Sends::threadOf($lead) : null),
            ], $validated['draft'] ?? false);

            $cards[] = MailCard::message($user, $message->load(['lead', 'mailbox']));
        }

        $text = sprintf('%d mails %s.', count($cards), ($validated['draft'] ?? false) ? 'saved as drafts' : 'queued');

        if ($skipped !== []) {
            $text .= ' Skipped: '.implode('; ', $skipped).'.';
        }

        return Reply::make($text, ['mails' => $cards, 'skipped' => $skipped, 'url' => rtrim(config('app.url'), '/').'/messages']);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mails' => $schema->array()->description('[{lead_id, subject, body, step?}], subject and body final, plain text.')->required(),
            'mailbox_id' => $schema->integer()->description('Send from this mailbox. Default: the box with the most room today.'),
            'draft' => $schema->boolean()->description('Save as drafts instead of queueing.')->default(false),
        ];
    }
}
