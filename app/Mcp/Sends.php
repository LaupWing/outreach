<?php

namespace App\Mcp;

use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\User;
use App\Support\Mail\Outbox;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * What send_step and send share: picking a box, queueing or drafting, and the answer.
 */
class Sends
{
    /**
     * @param  array{subject: string, body: string, step: int, thread_id: string|null}  $attributes
     */
    public static function queue(Outbox $outbox, User $user, Lead $lead, array $attributes, ?int $mailboxId, bool $draft): Response|ResponseFactory
    {
        $mailbox = $outbox->pick($user, $mailboxId);

        if ($mailbox === null) {
            return Response::error($mailboxId === null
                ? 'Every mailbox is full for today (or paused); try again tomorrow or raise a limit.'
                : "Mailbox {$mailboxId} is paused, full for today, or not on this account.");
        }

        if ($draft) {
            $message = $lead->messages()->create([
                'user_id' => $lead->user_id,
                'mailbox_id' => $mailbox->id,
                'subject' => $attributes['subject'],
                'body' => $attributes['body'],
                'step' => $attributes['step'],
                'thread_id' => $attributes['thread_id'] ?? 'thr_'.Str::lower(Str::random(5)),
                'status' => MessageStatus::Draft,
            ]);

            return Response::make(Response::text("Draft {$message->id} saved for {$lead->company}; send it with send_draft or from the app."))
                ->withStructuredContent(MailCard::message($user, $message->load(['lead', 'mailbox'])));
        }

        $message = $outbox->queue($lead, $mailbox, $attributes);
        $sendsAt = $message->send_after->setTimezone($user->send_timezone);

        return Response::make(Response::text(sprintf(
            'Queued for %s from %s, sends %s at %s.',
            $lead->company, $mailbox->address, $sendsAt->isToday() ? 'today' : $sendsAt->format('D j M'), $sendsAt->format('H:i'),
        )))->withStructuredContent(MailCard::message($user, $message->load(['lead', 'mailbox'])));
    }

    /**
     * The thread the lead's last mail went in, so a follow-up lands under it.
     */
    public static function threadOf(Lead $lead): ?string
    {
        return $lead->messages()
            ->reorder()
            ->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied])
            ->latest('sent_at')
            ->latest('id')
            ->value('thread_id');
    }
}
