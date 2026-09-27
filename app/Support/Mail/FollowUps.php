<?php

namespace App\Support\Mail;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps sequences moving: a lead whose next step is due and who has not replied
 * gets that step queued from the offer's sequence, in the thread of the first mail.
 */
class FollowUps
{
    public function __construct(private Outbox $outbox) {}

    /**
     * Leads whose follow-up moment has passed and who are still in the sequence,
     * skipping any that already have a mail waiting in the outbox.
     *
     * @return Collection<int, Lead>
     */
    public function due(User $user): Collection
    {
        return $user->leads()
            ->whereIn('status', [LeadStatus::Emailed, LeadStatus::FollowedUp])
            ->whereNotNull('offer_id')
            ->whereNotNull('email')
            ->where('next_action_at', '<=', now())
            ->whereDoesntHave('messages', fn ($query) => $query->where('status', MessageStatus::Queued))
            ->orderBy('next_action_at')
            ->get();
    }

    /**
     * Queue the lead's next step. Null when the sequence has no next step or every
     * mailbox is full for today; the lead then stays due and is picked up later.
     */
    public function queue(Lead $lead): ?Message
    {
        $lastStep = (int) $lead->messages()
            ->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied])
            ->max('step');

        $next = $lead->user->sequenceSteps()
            ->where('offer_id', $lead->offer_id)
            ->where('step', $lastStep + 1)
            ->first();

        if ($next === null) {
            // The sequence is done; nothing more to plan.
            $lead->update(['next_action_at' => null]);

            return null;
        }

        // A tag nobody filled yet: the lead stays due until the AI or a person adds the fact.
        if (Placeholders::missing($next->subject.' '.$next->body, $lead) !== []) {
            return null;
        }

        $mailbox = $this->outbox->pick($lead->user);

        if ($mailbox === null) {
            return null;
        }

        $thread = $lead->messages()
            ->reorder()
            ->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied])
            ->latest('sent_at')
            ->value('thread_id');

        return $this->outbox->queue($lead, $mailbox, [
            'subject' => Placeholders::fill($next->subject, $lead),
            'body' => Placeholders::fill($next->body, $lead),
            'step' => $next->step,
            'thread_id' => $thread,
        ]);
    }

    /**
     * @return array{queued: int, finished: int, waiting: int}
     */
    public function run(User $user): array
    {
        $counts = ['queued' => 0, 'finished' => 0, 'waiting' => 0];

        foreach ($this->due($user) as $lead) {
            $message = $this->queue($lead);

            if ($message !== null) {
                $counts['queued']++;
            } elseif ($lead->refresh()->next_action_at === null) {
                $counts['finished']++;
            } else {
                $counts['waiting']++;
            }
        }

        return $counts;
    }
}
