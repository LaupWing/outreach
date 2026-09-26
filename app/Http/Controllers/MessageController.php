<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    /**
     * Every mail that went out, newest first, with its reply.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Message::class);

        return Inertia::render('messages/index', [
            'messages' => Message::query()->latest('sent_at')->latest('id')->get(),
            'leads' => Lead::query()->select(['id', 'company', 'email'])->get(),
            'mailboxes' => Mailbox::query()->select(['id', 'address'])->get(),
        ]);
    }
}
