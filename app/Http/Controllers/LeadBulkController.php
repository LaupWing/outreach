<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\BulkLeadRequest;
use App\Support\Blocklist;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadBulkController extends Controller
{
    /**
     * The same change on many leads at once: an offer, a status, or gone.
     */
    public function __invoke(BulkLeadRequest $request): RedirectResponse
    {
        $leads = $request->user()->leads()->whereIn('id', $request->validated('ids'));
        $count = $leads->count();

        $message = match ($request->validated('action')) {
            'assign_offer' => tap("Offer set on {$count} leads.", fn () => $leads->update(['offer_id' => $request->validated('offer_id')])),
            'set_status' => tap("Status set on {$count} leads.", fn () => $leads->update(['status' => $request->validated('status')])),
            'delete' => tap("{$count} leads deleted.", fn () => $leads->delete()),
            'block' => tap("{$count} leads will never be mailed again.", fn () => $leads->get()->each(fn ($lead) => Blocklist::block($lead, 'Blocked by hand.'))),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
