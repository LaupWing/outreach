<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Support\MailboxConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MailboxConnectionController extends Controller
{
    /**
     * "Test connection": log in over SMTP and IMAP, remember the outcome on the box.
     */
    public function __invoke(Mailbox $mailbox, MailboxConnection $connection): RedirectResponse
    {
        Gate::authorize('update', $mailbox);

        $error = $connection->check($mailbox);

        $mailbox->forceFill([
            'connection_checked_at' => now(),
            'connection_error' => $error,
        ])->save();

        Inertia::flash('toast', $error === null
            ? ['type' => 'success', 'message' => __('Connected: SMTP and IMAP both log in.')]
            : ['type' => 'error', 'message' => $error]);

        return back();
    }
}
