<?php

namespace App\Http\Controllers;

use App\Http\Requests\Messages\StoreLeadMessageRequest;
use App\Models\Lead;
use App\Support\Mail\Outbox;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadMessageController extends Controller
{
    /**
     * Write one mail to a lead: a step of its offer's sequence, or a free-form one-off.
     *
     * It joins the outbox and leaves at the mailbox's next free moment in the sending
     * window; the lead moves along once it has actually gone out.
     */
    public function store(StoreLeadMessageRequest $request, Lead $lead, Outbox $outbox): RedirectResponse
    {
        // Validation already made sure a box with room exists.
        $mailbox = $request->mailbox();

        abort_if($mailbox === null, 422);

        $step = $request->filled('step') ? $request->integer('step') : 0;

        $message = $outbox->queue($lead, $mailbox, [
            ...$request->safe()->only(['subject', 'body']),
            'step' => $step,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':what queued, sends :when.', [
                'what' => $step === 0 ? 'Mail' : "Step {$step}",
                'when' => $message->send_after->setTimezone(config('outreach.window.timezone'))->isToday()
                    ? 'at '.$message->send_after->setTimezone(config('outreach.window.timezone'))->format('H:i')
                    : $message->send_after->setTimezone(config('outreach.window.timezone'))->format('D H:i'),
            ]),
        ]);

        return back();
    }
}
