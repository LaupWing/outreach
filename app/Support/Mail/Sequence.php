<?php

namespace App\Support\Mail;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Message;

/**
 * Whether a lead is still being worked by its sequence, and stopping it when not.
 */
class Sequence
{
    /**
     * Statuses in which the next step may still go out.
     */
    public static function running(Lead $lead): bool
    {
        return in_array($lead->status, [LeadStatus::New, LeadStatus::Emailed, LeadStatus::FollowedUp], true);
    }

    /**
     * The lead answered, bought, said no or bounced: waiting sequence mail is cancelled
     * and no next step is planned. Answers in the conversation stay in the outbox.
     */
    public static function stop(Lead $lead): int
    {
        $lead->forceFill(['next_action_at' => null])->save();

        return $lead->messages()
            ->where('status', MessageStatus::Queued)
            ->where('is_reply', false)
            ->where('step', '>=', 1)
            ->delete();
    }

    /**
     * Call after a status change: stops the sequence when the lead left it.
     */
    public static function afterStatusChange(Lead $lead): void
    {
        if (! self::running($lead)) {
            self::stop($lead);
        }
    }

    /**
     * A sequence step for a lead that is no longer in its sequence.
     */
    public static function isStale(Message $message): bool
    {
        return $message->step >= 1 && ! $message->is_reply && ! self::running($message->lead);
    }
}
