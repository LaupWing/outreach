<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Mcp\Account;
use App\Mcp\Reply;
use App\Models\Lead;
use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('outbox')]
#[Description('Everything still to go out: the mail waiting in the outbox per sending day and mailbox, the follow-ups the app will queue by itself later, and the waiting mails themselves (soonest first, with the id update_mails takes). Use it to see how full the coming days are before queueing more.')]
#[IsReadOnly]
class Outbox extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);
        $day = fn (?CarbonInterface $moment) => $moment?->copy()->setTimezone($user->send_timezone)->toDateString() ?? 'unplanned';

        $queued = $user->messages()
            ->with(['lead:id,company,email', 'mailbox:id,address'])
            ->where('status', MessageStatus::Queued)
            ->orderByRaw('send_after is null')
            ->orderBy('send_after')
            ->orderBy('id')
            ->get();

        // Follow-ups that are not in the outbox yet; the app queues them once they are due.
        $planned = $user->leads()
            ->whereIn('status', [LeadStatus::Emailed, LeadStatus::FollowedUp])
            ->whereHas('offer', fn ($query) => $query->where('auto_follow_up', true))
            ->where('next_action_at', '>', now())
            ->whereDoesntHave('messages', fn ($query) => $query->where('status', MessageStatus::Queued))
            ->get(['id', 'next_action_at']);

        $days = $queued->groupBy(fn (Message $message) => $day($message->send_after))
            ->map(fn ($messages, string $date) => [
                'date' => $date,
                'queued' => $messages->count(),
                'by_mailbox' => $messages->countBy(fn (Message $message) => $message->mailbox?->address ?? 'none')->all(),
            ]);

        $followUps = $planned->countBy(fn (Lead $lead) => $day($lead->next_action_at))->sortKeys();

        $mailboxes = $user->mailboxes()->orderBy('id')->get()->map(fn ($mailbox) => [
            'address' => $mailbox->address,
            'status' => $mailbox->status->value,
            'sent_today' => $mailbox->sent_today,
            'limit_today' => $mailbox->limitToday(),
            'queued' => $queued->where('mailbox_id', $mailbox->id)->count(),
        ]);

        $summary = sprintf(
            '%d mails in the outbox over %d sending days, %d follow-ups planned after that. Room today: %d of %d.',
            $queued->count(),
            $days->count(),
            $planned->count(),
            max(0, $mailboxes->sum('limit_today') - $mailboxes->sum('sent_today')),
            $mailboxes->sum('limit_today'),
        );

        return Reply::make($summary, [
            'total_queued' => $queued->count(),
            'total_planned_follow_ups' => $planned->count(),
            'timezone' => $user->send_timezone,
            'sending_hours' => sprintf('%02d:00-%02d:00%s', $user->send_from, $user->send_until, $user->send_weekdays_only ? ', weekdays' : ''),
            'days' => $days->values()->all(),
            'planned_follow_ups' => $followUps->map(fn (int $count, string $date) => ['date' => $date, 'leads' => $count])->values()->all(),
            'mailboxes' => $mailboxes->all(),
            'messages' => $queued->take(100)->map(fn (Message $message) => [
                'id' => $message->id,
                'lead_id' => $message->lead_id,
                'company' => $message->lead?->company,
                'email' => $message->lead?->email,
                'step' => $message->step,
                'is_reply' => $message->is_reply,
                'subject' => $message->subject,
                'mailbox' => $message->mailbox?->address,
                'send_after' => $message->send_after?->copy()->setTimezone($user->send_timezone)->toIso8601String(),
            ])->values()->all(),
            'url' => rtrim(config('app.url'), '/').'/messages?status[]=queued',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
