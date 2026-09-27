<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;

/**
 * The mail as a card inside Claude: who gets it, from which box, subject and body,
 * whether it is a preview or already queued, and a button to edit it in the app.
 */
#[Description('A mail to a lead, rendered as a card with an edit link into Snelreach.')]
#[AppMeta]
class MailCardApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.mail-card-app', ['title' => 'Mail']);
    }
}
