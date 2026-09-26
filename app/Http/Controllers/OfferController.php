<?php

namespace App\Http\Controllers;

use App\Enums\NicheStatus;
use App\Http\Requests\Offers\StoreOfferRequest;
use App\Http\Requests\Offers\UpdateOfferRequest;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\SequenceStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    /**
     * The offers with their sequences and what the table and panel derive their counts from.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Offer::class);

        return Inertia::render('offers/index', [
            'offers' => Offer::query()
                ->select(['id', 'name', 'niche_id', 'description', 'status'])
                ->orderBy('name')
                ->get(),
            'niches' => Niche::query()
                ->select(['id', 'name', 'status', 'why', 'findings'])
                ->orderBy('name')
                ->get(),
            'leads' => Lead::query()
                ->select(['id', 'company', 'email', 'niche_id', 'offer_id', 'status'])
                ->get(),
            'messages' => Message::query()
                ->select(['id', 'lead_id', 'sent_at'])
                ->get(),
            'steps' => SequenceStep::query()
                ->select(['id', 'offer_id', 'step', 'days_after_previous', 'subject', 'body'])
                ->orderBy('offer_id')
                ->orderBy('step')
                ->get(),
        ]);
    }

    public function store(StoreOfferRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $offer = Offer::create([
                ...$request->safe()->only(['name', 'description', 'status']),
                'niche_id' => $this->resolveNicheId($request->validated()),
            ]);

            if ($request->has('first_step')) {
                $offer->steps()->create([
                    'step' => 1,
                    'days_after_previous' => 0,
                    ...$request->validated('first_step'),
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Offer added.']);

        return back();
    }

    public function update(UpdateOfferRequest $request, Offer $offer): RedirectResponse
    {
        $validated = $request->validated();
        $attributes = $request->safe()->only(['name', 'description', 'status']);

        if (($validated['niche_id'] ?? null) !== null || ($validated['new_niche'] ?? null) !== null) {
            $attributes['niche_id'] = $this->resolveNicheId($validated);
        }

        $offer->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Offer saved.']);

        return back();
    }

    public function destroy(Offer $offer): RedirectResponse
    {
        Gate::authorize('delete', $offer);

        $offer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Offer deleted.']);

        return back();
    }

    /**
     * The niche the offer belongs to: the given one, or a fresh idea from `new_niche`.
     *
     * @param  array{niche_id?: int|string|null, new_niche?: string|null}  $validated
     */
    private function resolveNicheId(array $validated): int
    {
        if (($validated['niche_id'] ?? null) !== null) {
            return (int) $validated['niche_id'];
        }

        return Niche::create([
            'name' => $validated['new_niche'],
            'status' => NicheStatus::Idea,
        ])->id;
    }
}
