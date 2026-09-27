<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Mcp\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('stats')]
#[Description('How the outreach is doing: per niche, per offer and per mailbox, for the last N days (default 30). Leads, mails sent, replies, customers, bounces and reply rate, plus today\'s room per mailbox.')]
#[IsReadOnly]
class Stats extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate(['days' => ['sometimes', 'integer', 'min:1', 'max:365']]);
        $since = now()->subDays($validated['days'] ?? 30);

        // Both relations and builders come through here; cloning works on either.
        $group = function ($leads, $messages) use ($since): array {
            $sent = (clone $messages)->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied, MessageStatus::Bounced])->where('sent_at', '>=', $since)->count();
            $replied = (clone $messages)->whereNotNull('reply_received_at')->where('reply_received_at', '>=', $since)->count();

            return [
                'leads' => (clone $leads)->count(),
                'with_email' => (clone $leads)->whereNotNull('email')->count(),
                'sent' => $sent,
                'replied' => $replied,
                'customers' => (clone $leads)->where('status', LeadStatus::Customer)->count(),
                'bounced' => (clone $messages)->where('status', MessageStatus::Bounced)->where('sent_at', '>=', $since)->count(),
                'reply_rate' => $sent === 0 ? null : round($replied / $sent, 3),
            ];
        };

        $niches = $user->niches()->orderBy('name')->get()->map(fn ($niche) => [
            'id' => $niche->id,
            'name' => $niche->name,
            'status' => $niche->status->value,
            ...$group($user->leads()->where('niche_id', $niche->id), $user->messages()->whereIn('lead_id', $user->leads()->where('niche_id', $niche->id)->select('id'))),
        ]);

        $offers = $user->offers()->orderBy('name')->get()->map(fn ($offer) => [
            'id' => $offer->id,
            'name' => $offer->name,
            'status' => $offer->status->value,
            ...$group($user->leads()->where('offer_id', $offer->id), $user->messages()->whereIn('lead_id', $user->leads()->where('offer_id', $offer->id)->select('id'))),
        ]);

        $mailboxes = $user->mailboxes()->orderBy('id')->get()->map(fn ($mailbox) => [
            'id' => $mailbox->id,
            'address' => $mailbox->address,
            'status' => $mailbox->status->value,
            'sent_today' => $mailbox->sent_today,
            'limit_today' => $mailbox->limitToday(),
            'sent' => $mailbox->messages()->whereIn('status', [MessageStatus::Sent, MessageStatus::Replied, MessageStatus::Bounced])->where('sent_at', '>=', $since)->count(),
            'replied' => $mailbox->messages()->whereNotNull('reply_received_at')->where('reply_received_at', '>=', $since)->count(),
            'bounced' => $mailbox->messages()->where('status', MessageStatus::Bounced)->where('sent_at', '>=', $since)->count(),
        ]);

        $total = $group($user->leads(), $user->messages());

        return Response::make(Response::text(sprintf(
            'Last %d days: %d mails sent, %d replies (%s), %d customers, %d bounced. %d leads in total, %d with email.',
            $validated['days'] ?? 30, $total['sent'], $total['replied'],
            $total['reply_rate'] === null ? 'no rate yet' : round($total['reply_rate'] * 100).'%',
            $total['customers'], $total['bounced'], $total['leads'], $total['with_email'],
        )))->withStructuredContent([
            'days' => $validated['days'] ?? 30,
            'total' => $total,
            'niches' => $niches->all(),
            'offers' => $offers->all(),
            'mailboxes' => $mailboxes->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Look back this many days.')->default(30),
        ];
    }
}
