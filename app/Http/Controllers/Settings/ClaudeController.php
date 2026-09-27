<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\ClaudeConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClaudeController extends Controller
{
    /**
     * Disconnect one MCP client; it has to log in and approve again to come back.
     */
    public function destroy(Request $request, string $client): RedirectResponse
    {
        $revoked = ClaudeConnections::disconnect($request->user(), $client);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $revoked === 0 ? __('Nothing to disconnect.') : __('Disconnected.'),
        ]);

        return back();
    }
}
