<?php

namespace App\Http\Controllers;

use App\Http\Requests\Messages\StoreMessageReplyRequest;
use App\Models\Message;
use App\Support\Mail\Outbox;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MessageReplyController extends Controller
{
    /**
     * Answer a lead in the thread they wrote in, from the box that mail went out of.
     *
     * A reply is a conversation, not a campaign: it skips the spreading and goes out
     * on the sender's next tick, still inside the window.
     */
    public function store(StoreMessageReplyRequest $request, Message $message, Outbox $outbox): RedirectResponse
    {
        $outbox->queue($message->lead, $message->mailbox, [
            'subject' => 'Re: '.$message->subject,
            'body' => $request->string('body')->toString(),
            'step' => $message->step,
            'is_reply' => true,
            'thread_id' => $message->thread_id,
        ], rightAway: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply queued, goes out in a few minutes.')]);

        return back();
    }
}
