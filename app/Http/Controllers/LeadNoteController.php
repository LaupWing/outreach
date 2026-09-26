<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\StoreLeadNoteRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadNoteController extends Controller
{
    /**
     * Type a note on a lead; it shows up in the activity tab.
     */
    public function store(StoreLeadNoteRequest $request, Lead $lead): RedirectResponse
    {
        $lead->notes()->create($request->safe()->only(['body']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note added.')]);

        return back();
    }
}
