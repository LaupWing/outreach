<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Http\Requests\Messages\StoreLeadMessageRequest;
use App\Models\Lead;
use App\Models\SequenceStep;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LeadMessageController extends Controller
{
    /**
     * Write one mail to a lead: a step of its offer's sequence, or a free-form one-off.
     *
     * The message is recorded as sent right away and counts against the mailbox's
     * daily limit. Handing it to the mail provider is a later job; nothing leaves
     * the app yet.
     */
    public function store(StoreLeadMessageRequest $request, Lead $lead): RedirectResponse
    {
        // Validation already made sure a box with room exists.
        $mailbox = $request->mailbox();

        abort_if($mailbox === null, 422);

        $step = $request->filled('step') ? $request->integer('step') : null;

        DB::transaction(function () use ($request, $lead, $mailbox, $step): void {
            $lead->messages()->create([
                ...$request->safe()->only(['subject', 'body']),
                'mailbox_id' => $mailbox->id,
                'step' => $step ?? 0,
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
                'thread_id' => 'thr_'.Str::lower(Str::random(5)),
            ]);

            $mailbox->increment('sent_today');

            $lead->last_contact_at = now();

            if ($step !== null) {
                $lead->status = $step === 1 ? LeadStatus::Emailed : LeadStatus::FollowedUp;
                $lead->next_action_at = $this->followUpAt($lead, $step);
            }

            $lead->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $step === null ? __('Mail sent.') : __('Step :step sent.', ['step' => $step]),
        ]);

        return back();
    }

    /**
     * When the next step of the lead's offer is due, or null once the sequence is done.
     */
    private function followUpAt(Lead $lead, int $step): ?CarbonInterface
    {
        $next = SequenceStep::query()
            ->where('offer_id', $lead->offer_id)
            ->where('step', $step + 1)
            ->first();

        return $next === null ? null : now()->addDays($next->days_after_previous);
    }
}
