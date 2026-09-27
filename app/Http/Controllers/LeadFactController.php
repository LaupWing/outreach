<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\UpdateLeadFactsRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadFactController extends Controller
{
    /**
     * Replace the lead's facts: the values that fill the {{tags}} in its offer's mails.
     * Keys are tag names; blank values are dropped.
     */
    public function update(UpdateLeadFactsRequest $request, Lead $lead): RedirectResponse
    {
        $facts = collect($request->validated('facts'))
            ->mapWithKeys(fn (?string $value, string $key) => [trim($key) => trim((string) $value)])
            ->filter(fn (string $value, string $key) => $key !== '' && $value !== '')
            ->all();

        $lead->update(['facts' => $facts === [] ? null : $facts]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Facts saved.')]);

        return back();
    }
}
