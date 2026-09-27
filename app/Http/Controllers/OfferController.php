<?php

namespace App\Http\Controllers;

use App\Enums\NicheStatus;
use App\Http\Requests\Offers\StoreOfferRequest;
use App\Http\Requests\Offers\UpdateOfferRequest;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\User;
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
        $user = request()->user();

        return Inertia::render('offers/index', [
            'offers' => $user->offers()
                ->select(['id', 'name', 'niche_id', 'description', 'status'])
                ->orderBy('name')
                ->get(),
            'niches' => $user->niches()
                ->select(['id', 'name', 'status', 'why', 'findings'])
                ->orderBy('name')
                ->get(),
            'leads' => $user->leads()
                ->select(['id', 'company', 'email', 'niche_id', 'offer_id', 'status'])
                ->get(),
            'messages' => $user->messages()
                ->select(['id', 'lead_id', 'sent_at'])
                ->get(),
            'steps' => $user->sequenceSteps()
                ->select(['id', 'offer_id', 'step', 'days_after_previous', 'subject', 'body'])
                ->orderBy('offer_id')
                ->orderBy('step')
                ->get(),
        ]);
    }

    public function store(StoreOfferRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $placeholders = array_filter($request->validated('placeholders', []), fn (?string $value) => $value !== null && trim($value) !== '');

            $offer = $user->offers()->create([
                ...$request->safe()->only(['name', 'description', 'status']),
                'niche_id' => $this->resolveNicheId($user, $request->validated()),
                'placeholders' => $placeholders === [] ? null : $placeholders,
            ]);

            foreach (array_values($request->validated('steps', [])) as $index => $step) {
                $offer->steps()->create([
                    'user_id' => $offer->user_id,
                    'step' => $index + 1,
                    'days_after_previous' => $index === 0 ? 0 : ($step['days_after_previous'] ?? 3),
                    'subject' => $step['subject'],
                    'body' => $step['body'],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Offer added.']);

        return back();
    }

    public function update(UpdateOfferRequest $request, Offer $offer): RedirectResponse
    {
        $validated = $request->validated();
        $attributes = $request->safe()->only(['name', 'description', 'status', 'placeholders']);

        if (($validated['niche_id'] ?? null) !== null || ($validated['new_niche'] ?? null) !== null) {
            $attributes['niche_id'] = $this->resolveNicheId($request->user(), $validated);
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
    private function resolveNicheId(User $user, array $validated): int
    {
        if (($validated['niche_id'] ?? null) !== null) {
            return (int) $validated['niche_id'];
        }

        return $user->niches()->create([
            'name' => $validated['new_niche'],
            'status' => NicheStatus::Idea,
        ])->id;
    }
}
