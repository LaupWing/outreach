<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\Niche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /**
     * Every lead with what the table and the panel need around it. Filtering is
     * client-side for now; the lists are small enough until the scraper runs daily.
     */
    public function index(): Response
    {
        $user = request()->user();

        return Inertia::render('leads/index', [
            'leads' => $user->leads()->with('notes')->latest()->orderByDesc('id')->get(),
            'niches' => $user->niches()->orderBy('name')->get(),
            'offers' => $user->offers()->orderBy('name')->get(),
            'mailboxes' => $user->mailboxes()
                ->select(['id', 'address', 'type', 'daily_limit', 'sent_today', 'status'])
                ->orderBy('id')
                ->get(),
            'messages' => $user->messages()->orderBy('sent_at')->orderBy('id')->get(),
            'steps' => $user->sequenceSteps()->orderBy('offer_id')->orderBy('step')->get(),
            'scrapeRuns' => $user->scrapeRuns()->select(['id', 'query', 'place'])->get(),
        ]);
    }

    /**
     * Add a lead by hand, in an existing niche or a niche typed on the spot.
     */
    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $user = $request->user();

        $nicheId = $request->filled('new_niche')
            ? $user->niches()->create(['name' => $request->string('new_niche')->trim()->toString()])->id
            : $request->integer('niche_id');

        $user->leads()->create([
            ...$request->safe()->only(['company', 'email', 'phone', 'website', 'city', 'offer_id', 'status', 'hook']),
            'niche_id' => $nicheId,
            'source' => LeadSource::Manual,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead added.')]);

        return back();
    }

    /**
     * Change any of the lead's fields; the panel dropdowns send just one.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $user = $request->user();

        $lead->fill($request->safe()->only(['company', 'email', 'phone', 'website', 'city', 'niche_id', 'offer_id', 'status', 'hook']));

        if ($request->filled('new_niche')) {
            $lead->niche_id = $user->niches()->create(['name' => $request->string('new_niche')->trim()->toString()])->id;
            $lead->offer_id = null;
        }

        $lead->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead updated.')]);

        return back();
    }

    /**
     * Remove a lead; its messages and notes go with it.
     */
    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead removed.')]);

        return back();
    }
}
