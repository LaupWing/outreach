<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Http\Requests\Messages\StoreMessageReplyRequest;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MessageReplyController extends Controller
{
    /**
     * Answer a lead in the thread they wrote in, from the box that mail went out of.
     *
     * Recorded as sent; the actual delivery is a later job.
     */
    public function store(StoreMessageReplyRequest $request, Message $message): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $message, $user): void {
            $user->messages()->create([
                'lead_id' => $message->lead_id,
                'mailbox_id' => $message->mailbox_id,
                'step' => $message->step,
                'subject' => 'Re: '.$message->subject,
                'body' => $request->string('body')->toString(),
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
                'thread_id' => $message->thread_id,
            ]);

            $message->lead()->update(['last_contact_at' => now()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply sent.')]);

        return back();
    }
}
