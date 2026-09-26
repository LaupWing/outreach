<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\StoreLeadNoteRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LeadNoteController extends Controller
{
    /**
     * Type a note on a lead; it shows up in the activity tab.
     */
    public function store(StoreLeadNoteRequest $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $lead->notes()->create(['user_id' => $lead->user_id, ...$request->safe()->only(['body'])]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note added.')]);

        return back();
    }
}
