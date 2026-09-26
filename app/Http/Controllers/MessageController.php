<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    /**
     * Every mail that went out, newest first, with its reply.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('messages/index', [
            'messages' => $user->messages()->latest('sent_at')->latest('id')->get(),
            'leads' => $user->leads()->select(['id', 'company', 'email'])->get(),
            'mailboxes' => $user->mailboxes()->select(['id', 'address'])->get(),
        ]);
    }
}
