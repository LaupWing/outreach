<?php

namespace App\Mcp\Tools;

use App\Enums\MessageStatus;
use App\Mcp\Account;
use App\Mcp\MessageSummary;
use App\Support\Mail\Outbox;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('send_draft')]
#[Description('Put a draft in the outbox, optionally with a new subject or body first.')]
class SendDraft extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'message_id' => ['required', 'integer'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:20000'],
        ]);

        $draft = $user->messages()->with(['lead', 'mailbox'])->find($validated['message_id']);

        if ($draft === null || $draft->status !== MessageStatus::Draft) {
            return Response::error("No draft with id {$validated['message_id']} on this account.");
        }

        $mailbox = $outbox->pick($user, $draft->mailbox_id) ?? $outbox->pick($user);

        if ($mailbox === null) {
            return Response::error('Every mailbox is full for today (or paused); try again tomorrow.');
        }

        $message = $outbox->queue($draft->lead, $mailbox, [
            'subject' => $validated['subject'] ?? $draft->subject,
            'body' => $validated['body'] ?? $draft->body,
            'step' => $draft->step,
            'thread_id' => $draft->thread_id,
        ]);

        $draft->delete();

        $sendsAt = $message->send_after->setTimezone($user->send_timezone);

        return Response::make(Response::text(sprintf('Queued for %s, sends %s at %s.', $draft->lead->company, $sendsAt->isToday() ? 'today' : $sendsAt->format('D j M'), $sendsAt->format('H:i'))))
            ->withStructuredContent(MessageSummary::from($message));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message_id' => $schema->integer()->description('The draft to send.')->required(),
            'subject' => $schema->string()->description('Replace the subject first.'),
            'body' => $schema->string()->description('Replace the body first.'),
        ];
    }
}
