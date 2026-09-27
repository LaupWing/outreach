<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\Sends;
use App\Support\Mail\Outbox;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('send')]
#[Description('Send a mail you wrote yourself to a lead: subject and body as they should go out, plain text. A subject starting with "Re:" continues the lead\'s existing thread. Pass step to count it as that step of the sequence (so follow-ups continue from there); without it the mail is a one-off. With draft=true it is saved as a draft instead.')]
class SendMail extends Tool
{
    public function handle(Request $request, Outbox $outbox): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'lead_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'step' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'mailbox_id' => ['sometimes', 'integer'],
            'draft' => ['sometimes', 'boolean'],
        ]);

        $lead = $user->leads()->find($validated['lead_id']);

        if ($lead === null) {
            return Response::error("No lead with id {$validated['lead_id']} on this account.");
        }

        if ($lead->email === null) {
            return Response::error("{$lead->company} has no email address; enrich_lead or update it first.");
        }

        $continues = preg_match('/^re:/i', $validated['subject']) === 1 || ($validated['step'] ?? 1) > 1;

        return Sends::queue($outbox, $user, $lead, [
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'step' => $validated['step'] ?? 0,
            'thread_id' => $continues ? Sends::threadOf($lead) : null,
        ], $validated['mailbox_id'] ?? null, $validated['draft'] ?? false);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->description('The lead to mail.')->required(),
            'subject' => $schema->string()->description('The subject, final.')->required(),
            'body' => $schema->string()->description('The body, final, plain text with line breaks.')->required(),
            'step' => $schema->integer()->description('Count this as step N of the sequence. Omit for a one-off.'),
            'mailbox_id' => $schema->integer()->description('Send from this mailbox. Default: the box with the most room today.'),
            'draft' => $schema->boolean()->description('Save as a draft in the app instead of queueing it.')->default(false),
        ];
    }
}
