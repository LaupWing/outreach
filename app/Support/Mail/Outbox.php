<?php

namespace App\Support\Mail;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Where mail waits before it goes out. Queueing picks a moment inside the sending
 * window, spread so a mailbox sends evenly over the day; the scheduler then hands
 * every due message to SMTP and books the result on the message, mailbox and lead.
 */
class Outbox
{
    public function __construct(private MailSender $sender) {}

    /**
     * The chosen mailbox when it still has room today, or the box with the most room
     * when none was chosen. Null when nothing can send today.
     */
    public function pick(User $user, ?int $mailboxId = null): ?Mailbox
    {
        return $user->mailboxes()
            ->when($mailboxId !== null, fn ($query) => $query->whereKey($mailboxId))
            ->get()
            ->filter(fn (Mailbox $mailbox) => $this->roomToday($mailbox) > 0)
            ->sortByDesc(fn (Mailbox $mailbox) => $this->roomToday($mailbox))
            ->first();
    }

    /**
     * Mails the box may still queue for today: its limit minus what went out and what
     * already waits for today.
     */
    public function roomToday(Mailbox $mailbox): int
    {
        if (! $mailbox->canSend()) {
            return 0;
        }

        $window = SendingWindow::for($mailbox->user);
        $today = $window->on($window->now());

        $queuedToday = $mailbox->messages()
            ->where('status', MessageStatus::Queued)
            ->whereBetween('send_after', [$window->toUtc($today['start']), $window->toUtc($today['end'])])
            ->count();

        return max(0, $mailbox->limitToday() - $mailbox->sent_today - $queuedToday);
    }

    /**
     * Put a mail in the queue for the next free slot of its mailbox.
     *
     * @param  array{subject: string, body: string, step: int, thread_id?: string|null}  $attributes
     */
    public function queue(Lead $lead, Mailbox $mailbox, array $attributes, bool $rightAway = false): Message
    {
        $message = $lead->messages()->create([
            'user_id' => $lead->user_id,
            'mailbox_id' => $mailbox->id,
            'subject' => $attributes['subject'],
            'body' => $attributes['body'],
            'step' => $attributes['step'],
            'thread_id' => $attributes['thread_id'] ?? 'thr_'.Str::lower(Str::random(5)),
            'message_id' => $this->messageIdFor($mailbox),
            'status' => MessageStatus::Queued,
            'send_after' => $rightAway ? now() : $this->slotFor($mailbox),
        ]);

        return $message;
    }

    /**
     * Every queued message whose moment has come, oldest first.
     *
     * @return Collection<int, Message>
     */
    public function due(): Collection
    {
        return Message::query()
            ->with(['lead', 'mailbox'])
            ->where('status', MessageStatus::Queued)
            ->where('send_after', '<=', now())
            ->orderBy('send_after')
            ->orderBy('id')
            ->get();
    }

    /**
     * Hand one queued message to SMTP. A box without room pushes the mail to the next
     * window instead of sending; a refusal marks the message failed with the reason.
     * Returns the status the message ended up in.
     */
    public function send(Message $message): MessageStatus
    {
        $mailbox = $message->mailbox;
        $lead = $message->lead;

        if ($lead->email === null) {
            return $this->fail($message, 'The lead has no email address.');
        }

        $window = SendingWindow::for($mailbox->user);

        if (! $window->isOpen()) {
            $message->update(['send_after' => $window->toUtc($window->inside($window->now()))]);

            return MessageStatus::Queued;
        }

        if (! $mailbox->canSend() || $mailbox->sent_today >= $mailbox->limitToday()) {
            $message->update(['send_after' => $window->toUtc($window->nextAfter($window->now()))]);

            return MessageStatus::Queued;
        }

        $inReplyTo = $message->newQuery()
            ->where('thread_id', $message->thread_id)
            ->where('status', '!=', MessageStatus::Queued)
            ->whereKeyNot($message->id)
            ->whereNotNull('message_id')
            ->latest('sent_at')
            ->value('message_id');

        try {
            $this->sender->send($message, $inReplyTo);
        } catch (Throwable $exception) {
            Log::warning('Outbox: sending failed', ['message' => $message->id, 'error' => $exception->getMessage()]);

            return $this->fail($message, Str::of($exception->getMessage())->before("\n")->trim()->limit(250)->toString());
        }

        DB::transaction(function () use ($message, $mailbox, $lead): void {
            $message->update([
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
                'send_after' => null,
                'error' => null,
            ]);

            $mailbox->recordSent();

            $this->bookOnLead($lead, $message);
        });

        return MessageStatus::Sent;
    }

    /**
     * The next moment the mailbox can send: an even interval after the last mail
     * it has waiting, inside the window, with a little jitter so it is not a clock.
     * Returned in the app timezone, ready to save.
     */
    public function slotFor(Mailbox $mailbox): CarbonImmutable
    {
        $window = SendingWindow::for($mailbox->user);

        $lastQueued = $mailbox->messages()
            ->where('status', MessageStatus::Queued)
            ->max('send_after');

        $now = $window->now();
        $interval = (int) max(60, $window->length() / max(1, $mailbox->limitToday()));

        $candidate = $now;

        if ($lastQueued !== null) {
            $candidate = $candidate->max(CarbonImmutable::parse($lastQueued, config('app.timezone'))->setTimezone($now->getTimezone())->addSeconds($interval));
        }

        $candidate = $candidate->addSeconds(random_int(0, intdiv($interval, 3)));

        return $window->toUtc($window->inside($candidate));
    }

    /**
     * Sent, and the lead moves along: first step means emailed, a later one followed
     * up, and the next step is planned from the offer's sequence. A lead that already
     * replied, bought or said no keeps its status.
     */
    private function bookOnLead(Lead $lead, Message $message): void
    {
        $lead->last_contact_at = now();

        $inSequence = in_array($lead->status, [LeadStatus::New, LeadStatus::Emailed, LeadStatus::FollowedUp], true);

        if ($message->step >= 1 && $inSequence) {
            $lead->status = $message->step === 1 ? LeadStatus::Emailed : LeadStatus::FollowedUp;
            $lead->next_action_at = $this->nextStepDueAt($lead, $message->step);
        }

        $lead->save();
    }

    /**
     * When the next step of the lead's offer is due, or null once the sequence is done.
     */
    private function nextStepDueAt(Lead $lead, int $step): ?CarbonInterface
    {
        if ($lead->offer_id === null) {
            return null;
        }

        $next = $lead->user->sequenceSteps()
            ->where('offer_id', $lead->offer_id)
            ->where('step', $step + 1)
            ->first();

        return $next === null ? null : now()->addDays($next->days_after_previous);
    }

    private function fail(Message $message, string $reason): MessageStatus
    {
        $message->update(['status' => MessageStatus::Failed, 'error' => $reason]);

        return MessageStatus::Failed;
    }

    /**
     * A Message-ID on the mailbox's own domain, as a mail client would make one.
     */
    private function messageIdFor(Mailbox $mailbox): string
    {
        $domain = Str::after($mailbox->address, '@');

        return '<'.Str::uuid().'@'.$domain.'>';
    }
}
