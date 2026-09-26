<?php

namespace App\Http\Controllers;

use App\Enums\MailboxStatus;
use App\Http\Requests\Mailboxes\StoreMailboxRequest;
use App\Http\Requests\Mailboxes\UpdateMailboxRequest;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
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
        Gate::authorize('viewAny', Mailbox::class);

        return Inertia::render('mailboxes/index', [
            'mailboxes' => Mailbox::query()->orderBy('id')->get(),
            'messages' => Message::query()
                ->select(['id', 'lead_id', 'mailbox_id', 'step', 'subject', 'status', 'sent_at', 'reply_body', 'reply_received_at'])
                ->orderByDesc('sent_at')
                ->get()
                ->makeHidden('reply_body'),
            'leads' => Lead::query()->select(['id', 'company'])->get(),
        ]);
    }

    /**
     * Add a sending address.
     *
     * Gmail's OAuth connect is mocked for now: the address comes straight from the
     * form. IMAP hosts and the app password are validated but not stored; the
     * credentials land with the mail connection work.
     */
    public function store(StoreMailboxRequest $request): RedirectResponse
    {
        $warmUp = $request->boolean('warm_up');

        Mailbox::query()->create([
            ...$request->safe()->only(['type', 'address', 'daily_limit']),
            'status' => $warmUp ? MailboxStatus::WarmingUp : MailboxStatus::Active,
            'warm_up_started_at' => $warmUp ? now() : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox added.')]);

        return back();
    }

    /**
     * Change the limit, warm-up or status. Resuming a paused box puts it back where it was.
     */
    public function update(UpdateMailboxRequest $request, Mailbox $mailbox): RedirectResponse
    {
        $mailbox->fill($request->safe()->only(['daily_limit']));

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

        $mailbox->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox updated.')]);

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
