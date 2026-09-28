<?php

namespace App\Mcp\Tools;

use App\Enums\MessageStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\MailCard;
use App\Mcp\Reply;
use App\Mcp\Resources\MailCardApp;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;

#[Name('update_mails')]
#[Description('Change mails that have not gone out yet (queued or draft), one or many: per mail message_id plus a new subject, body or send_at (in the account\'s timezone, e.g. "2026-09-29 10:30"), or cancel=true to take it out of the outbox. Mail that already went out cannot be changed. Find message ids with leads_context.')]
#[RendersApp(resource: MailCardApp::class)]
class UpdateMails extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjects($request->all(), ['mails']));

        $validated = $request->validate([
            'mails' => ['required', 'array', 'min:1', 'max:200'],
            'mails.*.message_id' => ['required', 'integer'],
            'mails.*.subject' => ['sometimes', 'string', 'max:255'],
            'mails.*.body' => ['sometimes', 'string', 'max:20000'],
            'mails.*.send_at' => ['sometimes', 'date'],
            'mails.*.cancel' => ['sometimes', 'boolean'],
        ]);

        $cards = [];
        $cancelled = [];
        $skipped = [];

        foreach ($validated['mails'] as $row) {
            $message = $user->messages()->with(['lead', 'mailbox'])->find($row['message_id']);

            if ($message === null) {
                $skipped[] = "#{$row['message_id']}: not on this account";

                continue;
            }

            if (! in_array($message->status, [MessageStatus::Queued, MessageStatus::Draft], true)) {
                $skipped[] = "{$message->lead->company}: already {$message->status->value}, cannot be changed";

                continue;
            }

            if ($row['cancel'] ?? false) {
                $cancelled[] = $message->lead->company;
                $message->delete();

                continue;
            }

            $message->fill(collect($row)->only(['subject', 'body'])->all());

            if (isset($row['send_at']) && $message->status === MessageStatus::Queued) {
                $message->send_after = CarbonImmutable::parse($row['send_at'], $user->send_timezone)->setTimezone(config('app.timezone'));
            }

            $message->save();
            $cards[] = MailCard::message($user, $message);
        }

        $text = sprintf('%d mails changed, %d cancelled.', count($cards), count($cancelled));

        if ($cancelled !== []) {
            $text .= ' Cancelled: '.implode(', ', $cancelled).'.';
        }

        if ($skipped !== []) {
            $text .= ' Skipped: '.implode('; ', $skipped).'.';
        }

        return Reply::make($text, ['mails' => $cards, 'cancelled' => $cancelled, 'skipped' => $skipped, 'url' => rtrim(config('app.url'), '/').'/messages']);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mails' => $schema->array()->description('[{message_id, subject?, body?, send_at?, cancel?}]')->required(),
        ];
    }
}
