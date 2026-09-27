<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Models\Lead;
use App\Support\Mail\Placeholders;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('who_needs_follow_up')]
#[Description('Leads whose next sequence step is due and has not gone out: with the step, why it is waiting (offer has auto follow-up off, a tag is unfilled, no mailbox room) and what to do. Also lists leads that replied and are waiting for an answer.')]
#[IsReadOnly]
class WhoNeedsFollowUp extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $due = $user->leads()
            ->with('offer')
            ->whereIn('status', [LeadStatus::Emailed, LeadStatus::FollowedUp])
            ->whereNotNull('offer_id')
            ->where('next_action_at', '<=', now())
            ->whereDoesntHave('messages', fn ($query) => $query->where('status', MessageStatus::Queued))
            ->orderBy('next_action_at')
            ->limit(50)
            ->get()
            ->map(function (Lead $lead) use ($user) {
                $lastStep = (int) $lead->messages()->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied])->max('step');
                $next = $user->sequenceSteps()->where('offer_id', $lead->offer_id)->where('step', $lastStep + 1)->first();
                $missing = $next === null ? [] : Placeholders::missing($next->subject.' '.$next->body, $lead);

                $reason = match (true) {
                    $next === null => 'sequence finished; decide: mark "no", call, or write something else',
                    $lead->email === null => 'no email address',
                    $missing !== [] => 'unfilled tags: {{'.implode('}}, {{', $missing).'}}; pass them to send_step',
                    ! $lead->offer->auto_follow_up => 'offer has auto follow-up off; write it with send_step or send',
                    default => 'will go out automatically within the hour',
                };

                return [
                    ...LeadSummary::from($lead),
                    'facts' => $lead->facts,
                    'offer' => $lead->offer->name,
                    'next_step' => $next?->step,
                    'due_since' => $lead->next_action_at?->toJSON(),
                    'reason' => $reason,
                ];
            });

        $replied = $user->leads()
            ->where('status', LeadStatus::Replied)
            ->orderByDesc('last_contact_at')
            ->limit(50)
            ->get()
            ->map(fn (Lead $lead) => [...LeadSummary::from($lead), 'reason' => 'replied; answer them (send with a "Re:" subject) or set the status']);

        return Response::make(Response::text(sprintf('%d leads due for a follow-up, %d replied and waiting.', $due->count(), $replied->count())))
            ->withStructuredContent(['due' => $due->values()->all(), 'replied' => $replied->values()->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
