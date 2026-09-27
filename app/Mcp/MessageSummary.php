<?php

namespace App\Mcp;

use App\Models\Message;

/**
 * The shape of a message in tool responses.
 */
class MessageSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Message $message): array
    {
        return [
            'id' => $message->id,
            'lead_id' => $message->lead_id,
            'mailbox_id' => $message->mailbox_id,
            'step' => $message->step,
            'is_reply' => $message->is_reply,
            'subject' => $message->subject,
            'body' => $message->body,
            'status' => $message->status->value,
            'thread_id' => $message->thread_id,
            'send_after' => $message->send_after?->toJSON(),
            'sent_at' => $message->sent_at?->toJSON(),
            'error' => $message->error,
            'reply' => $message->reply,
        ];
    }
}
