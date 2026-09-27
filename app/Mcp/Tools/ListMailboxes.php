<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\Reply;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_mailboxes')]
#[Description('The mailboxes mail goes out from, with status, today\'s room, warm-up and whether the login works. Mailboxes are added in the app (they need an app password).')]
#[IsReadOnly]
class ListMailboxes extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $mailboxes = $user->mailboxes()->orderBy('id')->get()->map(fn ($mailbox) => [
            'id' => $mailbox->id,
            'address' => $mailbox->address,
            'status' => $mailbox->status->value,
            'sent_today' => $mailbox->sent_today,
            'limit_today' => $mailbox->limitToday(),
            'daily_limit' => $mailbox->daily_limit,
            'warm_up_started_at' => $mailbox->warm_up_started_at?->toJSON(),
            'login_ok' => $mailbox->connection_checked_at !== null && $mailbox->connection_error === null,
            'connection_error' => $mailbox->connection_error,
            'inbox_checked_at' => $mailbox->inbox_checked_at?->toJSON(),
        ]);

        return Reply::make(sprintf('%d mailboxes.', $mailboxes->count()), ['mailboxes' => $mailboxes->all(), 'url' => rtrim(config('app.url'), '/').'/mailboxes']);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
