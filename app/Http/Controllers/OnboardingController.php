<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\GoogleUpdateRequest;
use App\Models\Mailbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two steps before the app is usable: the Places key, then at least one mailbox.
 * Mailboxes are added through the normal mailbox endpoint; this only shows progress.
 */
class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isOnboarded()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/index', [
            'hasKey' => $user->google_places_key !== null,
            'mailboxes' => $user->mailboxes()->orderBy('id')->get(['id', 'address', 'status', 'daily_limit', 'connection_error']),
        ]);
    }

    /**
     * Step one: the key. Same rules as the settings page.
     */
    public function storeKey(GoogleUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill(['google_places_key' => $request->string('google_places_key')->toString()])->save();

        return back();
    }

    /**
     * Step two is done by adding a mailbox; this just moves on when both are there.
     */
    public function finish(Request $request): RedirectResponse
    {
        if (! $request->user()->isOnboarded()) {
            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You are set. Start with a scrape.')]);

        return redirect()->route('dashboard');
    }
}
