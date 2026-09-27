<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    /**
     * Every mail that went out, newest first, with its reply. Filtered and paged
     * on the server; the table scrolls the next page in.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(MessageStatus::class)],
            'mailbox' => ['sometimes', 'array'],
            'mailbox.*' => ['integer', Rule::exists(Mailbox::class, 'id')->where('user_id', $user->id)],
            'message' => ['sometimes', 'nullable', 'integer'],
        ]);

        $query = $user->messages()
            ->when($filters['status'] ?? [], fn ($query, array $statuses) => $query->whereIn('status', $statuses))
            ->when($filters['mailbox'] ?? [], fn ($query, array $mailboxes) => $query->whereIn('mailbox_id', $mailboxes));

        return Inertia::render('messages/index', [
            'messages' => Inertia::scroll($query->clone()
                ->with('lead:id,company,email')
                // Waiting mail first, soonest on top; then what went out, newest first.
                ->orderByRaw('case when status = ? then 0 else 1 end', [MessageStatus::Queued->value])
                ->orderByRaw('case when status = ? then send_after end asc', [MessageStatus::Queued->value])
                ->latest('sent_at')
                ->latest('id')
                ->paginate(50)),
            'counts' => [
                'total' => $query->clone()->count(),
                'replied' => $query->clone()->where('status', MessageStatus::Replied)->count(),
                'bounced' => $query->clone()->where('status', MessageStatus::Bounced)->count(),
            ],
            // The ?message deep link may point past the loaded page, so the panel gets it on its own.
            'linked' => isset($filters['message'])
                ? $user->messages()->with('lead:id,company,email')->find($filters['message'])
                : null,
            'mailboxes' => $user->mailboxes()->select(['id', 'address'])->get(),
        ]);
    }
}
