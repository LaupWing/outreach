<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\MessageSummary;
use App\Mcp\Reply;
use App\Mcp\Resources\LeadListApp;
use App\Support\Mail\InboxCheck;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('check_inbox')]
#[Description('Read every mailbox now for replies and bounces (the app also does this every ten minutes) and return the replies that are waiting for an answer: each with the lead, what was sent and what they wrote back.')]
#[IsOpenWorld]
#[IsIdempotent]
#[RendersApp(resource: LeadListApp::class)]
class CheckInbox extends Tool
{
    public function handle(Request $request, InboxCheck $check): Response|ResponseFactory
    {
        $user = Account::for($request);

        $totals = ['replies' => 0, 'bounces' => 0, 'errors' => []];

        $mailboxes = $user->mailboxes()->whereNotNull('connection_checked_at')->whereNull('connection_error')->get();

        foreach ($mailboxes as $mailbox) {
            $result = $check->run($mailbox);

            if ($result['error'] !== null) {
                $totals['errors'][] = "{$mailbox->address}: {$result['error']}";

                continue;
            }

            $totals['replies'] += $result['replies'];
            $totals['bounces'] += $result['bounces'];
        }

        // Replies nobody answered yet: no mail of ours in the thread after the reply came in.
        $waiting = $user->messages()
            ->with('lead:id,company,email,status')
            ->whereNotNull('reply_received_at')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('messages as later')
                ->whereColumn('later.thread_id', 'messages.thread_id')
                ->whereColumn('later.id', '>', 'messages.id'))
            ->latest('reply_received_at')
            ->limit(50)
            ->get();

        $text = sprintf(
            'Checked %d mailbox%s: %d new repl%s, %d bounce%s. %d repl%s waiting for an answer.%s',
            $mailboxes->count(), $mailboxes->count() === 1 ? '' : 'es',
            $totals['replies'], $totals['replies'] === 1 ? 'y' : 'ies',
            $totals['bounces'], $totals['bounces'] === 1 ? '' : 's',
            $waiting->count(), $waiting->count() === 1 ? 'y' : 'ies',
            $totals['errors'] === [] ? '' : ' Errors: '.implode('; ', $totals['errors']),
        );

        return Reply::make($text, [
            'new_replies' => $totals['replies'],
            'new_bounces' => $totals['bounces'],
            'errors' => $totals['errors'],
            'url' => rtrim(config('app.url'), '/').'/inbox',
            'waiting' => $waiting->map(fn ($message) => [
                ...MessageSummary::from($message),
                'company' => $message->lead?->company,
                'email' => $message->lead?->email,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
