<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
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
        $user = $request->user();

        return Inertia::render('dashboard', [
            'leads' => $user->leads()
                ->select(['id', 'niche_id', 'offer_id', 'status', 'next_action_at'])
                ->get(),
            'messages' => $user->messages()
                ->whereNotNull('sent_at')
                ->select(['id', 'lead_id', 'mailbox_id', 'status', 'sent_at', 'reply_body', 'reply_received_at'])
                ->get(),
            'mailboxes' => $user->mailboxes()->get(),
            'niches' => $user->niches()->select(['id', 'name'])->get(),
            'offers' => $user->offers()->select(['id', 'name'])->get(),
            'usage' => PlacesBudget::current($user),
            'due' => $user->leads()->where('next_action_at', '<=', now())->count(),
        ]);
    }
}
