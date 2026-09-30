<?php

namespace App\Support\Mail;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Support\Blocklist;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads new mail in a mailbox and books it on our side: a reply from a lead lands
 * on the mail it answers and stops the follow-up; a delivery failure marks the
 * mail bounced and the lead undeliverable. Anything else is left alone.
 */
class InboxCheck
{
    public function __construct(private MailboxReader $reader) {}

    /**
     * @return array{replies: int, bounces: int, skipped: int, error: string|null}
     */
    public function run(Mailbox $mailbox): array
    {
        $counts = ['replies' => 0, 'bounces' => 0, 'skipped' => 0, 'error' => null];

        try {
            $mail = $this->reader->newMail($mailbox);
        } catch (Throwable $exception) {
            Log::warning('Inbox check failed', ['mailbox' => $mailbox->id, 'error' => $exception->getMessage()]);

            $counts['error'] = trim(strtok($exception->getMessage(), "\n") ?: 'could not read the inbox');

            // The login failed for real: say so on the mailbox, the next round tries again.
            $mailbox->forceFill(['connection_checked_at' => now(), 'connection_error' => 'IMAP: '.$counts['error']])->save();

            return $counts;
        }

        // Reading worked, so the login does: that is the connection check, kept current.
        $mailbox->connection_checked_at = now();
        $mailbox->connection_error = null;

        foreach ($mail as $incoming) {
            $outcome = $incoming->autoSubmitted ? 'skipped' : $this->book($mailbox, $incoming);

            $counts[$outcome]++;

            $mailbox->last_seen_uid = max($mailbox->last_seen_uid ?? 0, $incoming->uid);
        }

        $mailbox->inbox_checked_at = now();
        $mailbox->save();

        return $counts;
    }

    /**
     * Book everything that came in since a moment, for mail sent before Snelreach was
     * in the loop. Does not move the mailbox's place; the regular check keeps its own.
     *
     * @return array{replies: int, bounces: int, skipped: int, error: string|null}
     */
    public function since(Mailbox $mailbox, CarbonImmutable $since): array
    {
        $counts = ['replies' => 0, 'bounces' => 0, 'skipped' => 0, 'error' => null];

        try {
            $mail = $this->reader->mailSince($mailbox, $since);
        } catch (Throwable $exception) {
            $counts['error'] = trim(strtok($exception->getMessage(), "\n") ?: 'could not read the inbox');

            return $counts;
        }

        foreach ($mail as $incoming) {
            $counts[$incoming->autoSubmitted ? 'skipped' : $this->book($mailbox, $incoming)]++;
        }

        return $counts;
    }

    /**
     * @return 'replies'|'bounces'|'skipped'
     */
    private function book(Mailbox $mailbox, IncomingMail $incoming): string
    {
        if ($incoming->isBounce()) {
            $original = $this->byReference($mailbox, $incoming) ?? $this->byAddressInText($mailbox, $incoming);

            if ($original === null) {
                return 'skipped';
            }

            DB::transaction(function () use ($original): void {
                $original->update(['status' => MessageStatus::Bounced]);
                $original->lead()->update(['status' => LeadStatus::Undeliverable, 'next_action_at' => null]);
            });

            Sequence::stop($original->lead);

            return 'bounces';
        }

        $original = $this->byReference($mailbox, $incoming) ?? $this->bySender($mailbox, $incoming);

        if ($original === null) {
            return 'skipped';
        }

        if ($original->reply_received_at !== null && $original->reply_received_at->equalTo($incoming->receivedAt)) {
            return 'skipped';
        }

        DB::transaction(function () use ($original, $incoming): void {
            $original->update([
                'status' => MessageStatus::Replied,
                'reply_body' => ReplyText::strip($incoming->text),
                'reply_received_at' => $incoming->receivedAt,
            ]);

            $original->lead()->update(['status' => LeadStatus::Replied, 'next_action_at' => null]);
        });

        // They answered: no more nudges, even the one already in the outbox.
        Sequence::stop($original->lead);

        // "Haal me uit je bestand": honoured right away, on this lead and forever after.
        if (Blocklist::asksToBeRemoved(ReplyText::strip($incoming->text))) {
            Blocklist::block($original->lead->refresh(), 'Asked to be removed in a reply.');
        }

        return 'replies';
    }

    /**
     * The mail this one answers, by the Message-IDs it refers to.
     */
    private function byReference(Mailbox $mailbox, IncomingMail $incoming): ?Message
    {
        if ($incoming->references === []) {
            return null;
        }

        return $mailbox->user->messages()
            ->whereIn('message_id', $incoming->references)
            ->where('status', '!=', MessageStatus::Queued)
            ->latest('sent_at')
            ->first();
    }

    /**
     * A reply without threading headers: the newest mail we sent to that address.
     */
    private function bySender(Mailbox $mailbox, IncomingMail $incoming): ?Message
    {
        $lead = $mailbox->user->leads()->whereRaw('lower(email) = ?', [strtolower($incoming->from)])->first()
            ?? $this->byDomain($mailbox, $incoming);

        return $lead === null ? null : $this->latestSentTo($lead);
    }

    /**
     * A person answering for the company from their own address (tim@ where we mailed
     * info@): the lead whose address or website has that domain. Only when exactly one
     * mailed lead does, and never for shared providers like gmail.com.
     */
    private function byDomain(Mailbox $mailbox, IncomingMail $incoming): ?Lead
    {
        $domain = Blocklist::domainOf($incoming->from);

        if ($domain === null) {
            return null;
        }

        $leads = $mailbox->user->leads()
            ->where(fn ($query) => $query
                ->whereRaw('lower(email) like ?', ['%@'.$domain])
                ->orWhereRaw('lower(website) = ?', [$domain]))
            ->whereHas('messages', fn ($query) => $query->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied]))
            ->limit(2)
            ->get();

        return $leads->count() === 1 ? $leads->first() : null;
    }

    /**
     * A delivery report without threading headers names the address that failed.
     */
    private function byAddressInText(Mailbox $mailbox, IncomingMail $incoming): ?Message
    {
        preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $incoming->text, $matches);

        $addresses = array_values(array_unique(array_map('strtolower', $matches[0])));

        if ($addresses === []) {
            return null;
        }

        $lead = $mailbox->user->leads()->whereIn('email', $addresses)->latest('last_contact_at')->first();

        return $lead === null ? null : $this->latestSentTo($lead);
    }

    private function latestSentTo(Lead $lead): ?Message
    {
        // The relation orders by step for the panel; here the newest mail wins.
        return $lead->messages()
            ->reorder()
            ->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied])
            ->latest('sent_at')
            ->latest('id')
            ->first();
    }
}
