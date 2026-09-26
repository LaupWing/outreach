<?php

namespace App\Http\Controllers;

use App\Http\Requests\SequenceSteps\ReorderSequenceStepsRequest;
use App\Http\Requests\SequenceSteps\StoreSequenceStepRequest;
use App\Http\Requests\SequenceSteps\UpdateSequenceStepRequest;
use App\Models\Offer;
use App\Models\SequenceStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SequenceStepController extends Controller
{
    /**
     * Steps beyond this many are not expected; the offset keeps a renumber clear of the (offer_id, step) unique index.
     */
    private const int RENUMBER_OFFSET = 10000;

    /**
     * Append a mail to the end of the offer's sequence.
     */
    public function store(StoreSequenceStepRequest $request, Offer $offer): RedirectResponse
    {
        $offer->steps()->create([
            'user_id' => $offer->user_id,
            'days_after_previous' => 0,
            ...$request->validated(),
            'step' => ((int) $offer->steps()->max('step')) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Step added.']);

        return back();
    }

    public function update(UpdateSequenceStepRequest $request, SequenceStep $step): RedirectResponse
    {
        $step->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Step saved.']);

        return back();
    }

    /**
     * Remove a mail and close the gap it leaves in the numbering.
     */
    public function destroy(SequenceStep $step): RedirectResponse
    {
        Gate::authorize('delete', $step);

        DB::transaction(function () use ($step): void {
            $step->delete();

            $this->renumber($step->offer, $step->offer->steps()->pluck('id')->all());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Step deleted.']);

        return back();
    }

    /**
     * Number the offer's steps 1..n in the given order.
     */
    public function reorder(ReorderSequenceStepsRequest $request, Offer $offer): RedirectResponse
    {
        DB::transaction(fn () => $this->renumber($offer, $request->orderedIds()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Step order saved.']);

        return back();
    }

    /**
     * Two passes, because the unique (offer_id, step) index would trip on a swap.
     *
     * @param  list<int>  $orderedIds
     */
    private function renumber(Offer $offer, array $orderedIds): void
    {
        $offer->steps()->increment('step', self::RENUMBER_OFFSET);

        foreach ($orderedIds as $index => $id) {
            $offer->steps()->whereKey($id)->update(['step' => $index + 1]);
        }
    }
}
