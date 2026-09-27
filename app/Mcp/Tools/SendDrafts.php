<?php

namespace App\Mcp\Tools;

use App\Enums\MessageStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\MailCard;
use App\Mcp\Reply;
use App\Mcp\Resources\MailCardApp;
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

#[Name('send_drafts')]
#[Description('Put drafts in the outbox, one or many: per draft message_id, optionally with a new subject or body first.')]
#[RendersApp(resource: MailCardApp::class)]
class SendDrafts extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjects($request->all(), ['drafts']));

        $validated = $request->validate([
            'drafts' => ['required', 'array', 'min:1', 'max:200'],
            'drafts.*.message_id' => ['required', 'integer'],
            'drafts.*.subject' => ['sometimes', 'string', 'max:255'],
            'drafts.*.body' => ['sometimes', 'string', 'max:20000'],
        ]);

        $cards = [];
        $skipped = [];

        foreach ($validated['drafts'] as $row) {
            $draft = $user->messages()->with(['lead', 'mailbox'])->find($row['message_id']);

            if ($draft === null || $draft->status !== MessageStatus::Draft) {
                $skipped[] = "#{$row['message_id']}: no such draft";

                continue;
            }

            $mailbox = $outbox->pick($user, $draft->mailbox_id) ?? $outbox->pick($user);

            if ($mailbox === null) {
                $skipped[] = "{$draft->lead->company}: every mailbox is full for today";

                continue;
            }

            $message = $outbox->queue($draft->lead, $mailbox, [
                'subject' => $row['subject'] ?? $draft->subject,
                'body' => $row['body'] ?? $draft->body,
                'step' => $draft->step,
                'thread_id' => $draft->thread_id,
            ]);

            $draft->delete();

            $cards[] = MailCard::message($user, $message->load(['lead', 'mailbox']));
        }

        $text = sprintf('%d drafts queued.', count($cards));

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
            'drafts' => $schema->array()->description('[{message_id, subject?, body?}]')->required(),
        ];
    }
}
