<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Niche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /**
     * The table, fifty leads at a time, filtered on the server; the panel's
     * thread (mails and notes) only comes along for the lead that is open.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(LeadStatus::class)],
            'source' => ['sometimes', 'array'],
            'source.*' => [Rule::enum(LeadSource::class)],
            'niche' => ['sometimes', 'array'],
            'niche.*' => ['integer'],
            'offer' => ['sometimes', 'array'],
            'offer.*' => ['integer'],
            'city' => ['sometimes', 'array'],
            'city.*' => ['string', 'max:255'],
            'run' => ['sometimes', 'nullable', 'integer'],
            'lead' => ['sometimes', 'nullable', 'integer'],
        ]);

        $leads = $user->leads()
            ->when($filters['status'] ?? [], fn ($query, array $values) => $query->whereIn('status', $values))
            ->when($filters['source'] ?? [], fn ($query, array $values) => $query->whereIn('source', $values))
            ->when($filters['niche'] ?? [], fn ($query, array $values) => $query->whereIn('niche_id', $values))
            ->when($filters['offer'] ?? [], fn ($query, array $values) => $query->whereIn('offer_id', $values))
            ->when($filters['city'] ?? [], fn ($query, array $values) => $query->whereIn('city', $values))
            ->when($filters['run'] ?? null, fn ($query, int $run) => $query->where('scrape_run_id', $run));

        // The address that last mailed each lead rides along as a column; no messages needed for the table.
        // A scalar "= (subquery limit 1)" on purpose: MySQL refuses LIMIT inside an IN subquery.
        $withSentFrom = fn ($query) => $query->addSelect([
            'sent_from' => Mailbox::query()
                ->select('address')
                ->where('mailboxes.id', '=', DB::table('messages')
                    ->select('mailbox_id')
                    ->whereColumn('messages.lead_id', 'leads.id')
                    ->orderByDesc('sent_at')->orderByDesc('id')->limit(1))
                ->limit(1),
        ]);

        $linkedId = $filters['lead'] ?? null;

        return Inertia::render('leads/index', [
            'leads' => Inertia::scroll(
                (clone $leads)->tap($withSentFrom)->latest()->orderByDesc('id')->paginate(50)->withQueryString(),
            ),
            'total' => (clone $leads)->count(),
            // A deep link to a lead beyond the first page still opens its panel.
            'linked' => $linkedId === null ? null : $user->leads()->tap($withSentFrom)->find($linkedId),
            // The open lead's mails and notes; the page asks for them with `only: ['thread']` when a row is picked,
            // and every save that redirects back to ?lead=ID brings them along again.
            'thread' => $linkedId === null ? null : [
                'messages' => $user->messages()->where('lead_id', $linkedId)->orderBy('sent_at')->orderBy('id')->get(),
                'notes' => $user->leadNotes()->where('lead_id', $linkedId)->latest()->get(),
            ],
            'niches' => $user->niches()->orderBy('name')->get(),
            'offers' => $user->offers()->orderBy('name')->get(),
            'cities' => $user->leads()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'mailboxes' => $user->mailboxes()
                ->select(['id', 'address', 'type', 'daily_limit', 'sent_today', 'status'])
                ->orderBy('id')
                ->get(),
            'steps' => $user->sequenceSteps()->orderBy('offer_id')->orderBy('step')->get(),
            'run' => ($filters['run'] ?? null) === null ? null : $user->scrapeRuns()->select(['id', 'query', 'place'])->find($filters['run']),
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
