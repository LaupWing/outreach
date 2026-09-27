<?php

namespace App\Mcp;

use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Support\Mail\Outbox;
use Illuminate\Support\Str;

/**
 * What the sending tools share: queueing or drafting one mail, and the thread it joins.
 */
class Sends
{
    /**
     * @param  array{subject: string, body: string, step: int, thread_id: string|null}  $attributes
     */
    public static function queue(Outbox $outbox, Lead $lead, Mailbox $mailbox, array $attributes, bool $draft): Message
    {
        if ($draft) {
            return $lead->messages()->create([
                'user_id' => $lead->user_id,
                'mailbox_id' => $mailbox->id,
                'subject' => $attributes['subject'],
                'body' => $attributes['body'],
                'step' => $attributes['step'],
                'thread_id' => $attributes['thread_id'] ?? 'thr_'.Str::lower(Str::random(5)),
                'status' => MessageStatus::Draft,
            ]);
        }

        return $outbox->queue($lead, $mailbox, $attributes);
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
