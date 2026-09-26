<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Support\PlacesBudget;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Home: what waits on you today, and response per offer, niche and mailbox.
     * The page does the arithmetic; the counts here are the ones it cannot derive.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'leads' => Lead::query()
                ->select(['id', 'niche_id', 'offer_id', 'status', 'next_action_at'])
                ->get(),
            'messages' => Message::query()
                ->whereNotNull('sent_at')
                ->select(['id', 'lead_id', 'mailbox_id', 'status', 'sent_at', 'reply_body', 'reply_received_at'])
                ->get(),
            'mailboxes' => Mailbox::query()->get(),
            'niches' => Niche::query()->select(['id', 'name'])->get(),
            'offers' => Offer::query()->select(['id', 'name'])->get(),
            'usage' => PlacesBudget::current(),
            'due' => Lead::query()->where('next_action_at', '<=', now())->count(),
        ]);
    }
}
