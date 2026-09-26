<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * What waits on you: replies to answer, follow-ups due today, and bounces to fix.
     */
    public function __invoke(): Response
    {
        $user = request()->user();

        /** @var Collection<int, Lead> $leads */
        $leads = $user->leads()
            ->with('notes')
            ->where(fn ($query) => $query
                ->whereIn('status', [LeadStatus::Replied, LeadStatus::Undeliverable])
                ->orWhere('next_action_at', '<=', now()))
            ->orderByDesc('id')
            ->get();

        return Inertia::render('inbox/index', [
            'leads' => $leads,
            'queues' => [
                'replies' => $leads->where('status', LeadStatus::Replied)->values(),
                'due' => $leads->filter(fn (Lead $lead) => $lead->next_action_at?->lte(now()) ?? false)->values(),
                'bounces' => $leads->where('status', LeadStatus::Undeliverable)->values(),
            ],
            'messages' => $user->messages()
                ->whereIn('lead_id', $leads->modelKeys())
                ->orderBy('sent_at')
                ->orderBy('id')
                ->get(),
            'mailboxes' => $user->mailboxes()
                ->select(['id', 'address', 'type', 'daily_limit', 'sent_today', 'status'])
                ->orderBy('id')
                ->get(),
            'niches' => $user->niches()->orderBy('name')->get(),
            'offers' => $user->offers()->orderBy('name')->get(),
            'steps' => $user->sequenceSteps()->orderBy('offer_id')->orderBy('step')->get(),
        ]);
    }
}
