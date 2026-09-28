<?php

namespace App\Http\Controllers;

use App\Enums\MailboxStatus;
use App\Enums\MailboxType;
use App\Http\Requests\Mailboxes\StoreMailboxRequest;
use App\Http\Requests\Mailboxes\UpdateMailboxRequest;
use App\Models\Mailbox;
use App\Support\Mail\Outbox;
use App\Support\MailboxConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MailboxController extends Controller
{
    /**
     * The mailboxes with the messages they sent, so the page can count replies and bounces per box.
     */
    public function index(): Response
    {
        $user = request()->user();

        return Inertia::render('mailboxes/index', [
            'mailboxes' => $user->mailboxes()->orderBy('id')->get(),
            'messages' => $user->messages()
                ->select(['id', 'lead_id', 'mailbox_id', 'step', 'is_reply', 'subject', 'status', 'sent_at', 'reply_body', 'reply_received_at'])
                ->orderByDesc('sent_at')
                ->get()
                ->makeHidden('reply_body'),
            'leads' => $user->leads()->select(['id', 'company'])->get(),
        ]);
    }

    /**
     * Add a sending address with its IMAP/SMTP credentials. The password is
     * stored encrypted; "Test connection" proves it works.
     */
    public function store(StoreMailboxRequest $request, MailboxConnection $connection): RedirectResponse
    {
        $user = $request->user();

        $warmUp = $request->boolean('warm_up');

        $mailbox = $user->mailboxes()->create([
            ...$request->safe()->only(['address', 'imap_host', 'imap_port', 'smtp_host', 'smtp_port', 'password', 'daily_limit']),
            'type' => MailboxType::Imap,
            'username' => $request->string('username')->toString() ?: $request->string('address')->toString(),
            'status' => $warmUp ? MailboxStatus::WarmingUp : MailboxStatus::Active,
            'warm_up_started_at' => $warmUp ? now() : null,
        ]);

        // Prove the credentials straight away, so a typo shows up here and not on the first send.
        $error = $connection->check($mailbox);
        $mailbox->forceFill(['connection_checked_at' => now(), 'connection_error' => $error])->save();

        Inertia::flash('toast', $error === null
            ? ['type' => 'success', 'message' => __('Mailbox added and connected.')]
            : ['type' => 'error', 'message' => __('Mailbox added, but it does not log in: :error', ['error' => $error])]);

        return back();
    }

    /**
     * Change the limit, warm-up or status. Resuming a paused box puts it back where it was.
     */
    public function update(UpdateMailboxRequest $request, Mailbox $mailbox, MailboxConnection $connection): RedirectResponse
    {
        $mailbox->fill($request->safe()->only(['daily_limit', 'imap_host', 'imap_port', 'smtp_host', 'smtp_port', 'username']));

        // A blank password on edit means "keep the one I have".
        if ($request->filled('password')) {
            $mailbox->password = $request->string('password')->toString();
        }

        // The dialog always sends every field; only a real change of login details
        // needs a new check, and that check runs right away, as on create.
        $credentialsChanged = $mailbox->isDirty(['imap_host', 'imap_port', 'smtp_host', 'smtp_port', 'username', 'password']);

        if ($request->has('warm_up')) {
            $warmUp = $request->boolean('warm_up');

            if ($warmUp && $mailbox->warm_up_started_at === null) {
                $mailbox->warm_up_started_at = now();
            }

            if ($mailbox->status !== MailboxStatus::Paused) {
                $mailbox->status = $warmUp ? MailboxStatus::WarmingUp : MailboxStatus::Active;
            }
        }

        if ($request->has('status')) {
            $status = MailboxStatus::from($request->string('status')->toString());
            $resuming = $mailbox->status === MailboxStatus::Paused && $status !== MailboxStatus::Paused;

            $mailbox->status = $resuming ? $mailbox->statusAfterResume() : $status;
        }

        // A new limit or warm-up changes how much fits in a day; plan what waits again.
        $pacingChanged = $mailbox->isDirty(['daily_limit', 'status', 'warm_up_started_at']);

        if ($credentialsChanged || $mailbox->connection_checked_at === null) {
            $mailbox->connection_error = $connection->check($mailbox);
            $mailbox->connection_checked_at = now();
        }

        $mailbox->save();

        if ($pacingChanged) {
            app(Outbox::class)->reschedule($mailbox);
        }

        Inertia::flash('toast', $mailbox->connection_error === null
            ? ['type' => 'success', 'message' => __('Mailbox updated.')]
            : ['type' => 'error', 'message' => __('Saved, but the login failed: :error', ['error' => $mailbox->connection_error])]);

        return back();
    }

    /**
     * Remove a mailbox; its messages keep their history.
     */
    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        Gate::authorize('delete', $mailbox);

        $mailbox->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox removed.')]);

        return back();
    }
}
