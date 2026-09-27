<?php

namespace App\Support\Mail;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls mail that went out before Snelreach kept track into the leads' threads, so
 * counts, follow-ups and replies line up with what really happened.
 */
class SentMailImport
{
    public function __construct(private MailboxReader $reader, private InboxCheck $inbox) {}

    /**
     * @param  bool  $createLeads  Make a lead for a recipient nobody knows yet (in the given niche).
     * @return array{imported: int, skipped: int, created: int, replies: int, bounces: int, error: string|null}
     */
    public function run(Mailbox $mailbox, CarbonImmutable $since, bool $createLeads = false, ?int $nicheId = null): array
    {
        $counts = ['imported' => 0, 'skipped' => 0, 'created' => 0, 'replies' => 0, 'bounces' => 0, 'error' => null];

        try {
            $sent = $this->reader->sentMail($mailbox, $since);
        } catch (Throwable $exception) {
            $counts['error'] = trim(strtok($exception->getMessage(), "\n") ?: 'could not read the sent folder');

            return $counts;
        }

        $user = $mailbox->user;

        foreach ($sent as $mail) {
            if ($mail->messageId !== null && $user->messages()->where('message_id', $mail->messageId)->exists()) {
                $counts['skipped']++;

                continue;
            }

            $lead = $user->leads()->whereIn('email', $mail->to)->first();

            if ($lead === null && $createLeads && $mail->to !== [] && $nicheId !== null) {
                $lead = $user->leads()->create([
                    'niche_id' => $nicheId,
                    'company' => Str::of($mail->to[0])->after('@')->before('.')->title()->toString(),
                    'email' => $mail->to[0],
                    'source' => LeadSource::Manual,
                ]);
                $counts['created']++;
            }

            if ($lead === null) {
                $counts['skipped']++;

                continue;
            }

            $this->record($mailbox, $lead, $mail);
            $counts['imported']++;
        }

        // Now the replies and bounces to those mails, which the regular check never saw.
        $caughtUp = $this->inbox->since($mailbox, $since);
        $counts['replies'] = $caughtUp['replies'];
        $counts['bounces'] = $caughtUp['bounces'];
        $counts['error'] = $caughtUp['error'];

        return $counts;
    }

    private function record(Mailbox $mailbox, Lead $lead, SentMail $mail): void
    {
        $previous = $lead->messages()
            ->reorder()
            ->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied, MessageStatus::Bounced])
            ->latest('sent_at')
            ->first();

        $continues = preg_match('/^re:/i', $mail->subject) === 1 && $previous !== null;

        $lead->messages()->create([
            'user_id' => $lead->user_id,
            'mailbox_id' => $mailbox->id,
            'step' => $continues ? $previous->step + 1 : ($previous === null ? 1 : 0),
            'subject' => $mail->subject,
            'body' => $mail->text,
            'status' => MessageStatus::Sent,
            'thread_id' => $continues ? $previous->thread_id : 'thr_'.Str::lower(Str::random(5)),
            'message_id' => $mail->messageId,
            'sent_at' => $mail->sentAt,
        ]);

        $lead->last_contact_at = $lead->last_contact_at === null ? $mail->sentAt : $lead->last_contact_at->max($mail->sentAt);

        if ($lead->status === LeadStatus::New) {
            $lead->status = LeadStatus::Emailed;
        } elseif ($lead->status === LeadStatus::Emailed && $continues) {
            $lead->status = LeadStatus::FollowedUp;
        }

        $lead->save();
    }
}
