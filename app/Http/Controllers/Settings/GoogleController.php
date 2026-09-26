<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GoogleUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoogleController extends Controller
{
    /**
     * The Google settings page. The key itself never goes to the browser, only whether one is set.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/google', [
            'hasKey' => $request->user()->google_places_key !== null,
        ]);
    }

    /**
     * Save the Places API key, encrypted.
     */
    public function update(GoogleUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill(['google_places_key' => $request->string('google_places_key')->toString()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Google key saved.')]);

        return back();
    }
}
