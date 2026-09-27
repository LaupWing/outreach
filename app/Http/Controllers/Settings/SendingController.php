<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SendingUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SendingController extends Controller
{
    /**
     * Save when queued mail may leave: the hours, the timezone, weekdays or not.
     * Mail already waiting keeps its slot; the next tick moves it if it now falls outside.
     */
    public function update(SendingUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sending hours saved.')]);

        return back();
    }
}
