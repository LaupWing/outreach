<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\SequenceStep;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * What waits on you: replies to answer, follow-ups due today, and bounces to fix.
     */
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Lead::class);

        /** @var Collection<int, Lead> $leads */
        $leads = Lead::query()
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
            'messages' => Message::query()
                ->whereIn('lead_id', $leads->modelKeys())
                ->orderBy('sent_at')
                ->orderBy('id')
                ->get(),
            'mailboxes' => Mailbox::query()
                ->select(['id', 'address', 'type', 'daily_limit', 'sent_today', 'status'])
                ->orderBy('id')
                ->get(),
            'niches' => Niche::query()->orderBy('name')->get(),
            'offers' => Offer::query()->orderBy('name')->get(),
            'steps' => SequenceStep::query()->orderBy('offer_id')->orderBy('step')->get(),
        ]);
    }
}
