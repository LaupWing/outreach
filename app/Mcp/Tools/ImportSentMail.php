<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Models\Niche;
use App\Support\Mail\SentMailImport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('import_sent_mail')]
#[Description('Bring mail that was sent by hand, before Snelreach, into the leads\' threads: reads the mailbox\'s sent folder since a date, attaches each mail to the lead with that address (or creates the lead when create_leads is set), then catches up on replies and bounces since that date. Safe to run again; known mails are skipped.')]
#[IsOpenWorld]
#[IsIdempotent]
class ImportSentMail extends Tool
{
    public function handle(Request $request, SentMailImport $import): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'since' => ['required', 'date'],
            'mailbox_id' => ['sometimes', 'integer'],
            'create_leads' => ['sometimes', 'boolean'],
            'niche_id' => ['required_if:create_leads,true', 'integer', Rule::exists(Niche::class, 'id')->where('user_id', $user->id)],
        ]);

        $mailboxes = $user->mailboxes()
            ->when(isset($validated['mailbox_id']), fn ($query) => $query->whereKey($validated['mailbox_id']))
            ->whereNotNull('connection_checked_at')
            ->whereNull('connection_error')
            ->get();

        if ($mailboxes->isEmpty()) {
            return Response::error('No mailbox with a working login to read from.');
        }

        $since = CarbonImmutable::parse($validated['since']);
        $totals = ['imported' => 0, 'skipped' => 0, 'created' => 0, 'replies' => 0, 'bounces' => 0, 'errors' => []];

        foreach ($mailboxes as $mailbox) {
            $result = $import->run($mailbox, $since, $validated['create_leads'] ?? false, $validated['niche_id'] ?? null);

            foreach (['imported', 'skipped', 'created', 'replies', 'bounces'] as $key) {
                $totals[$key] += $result[$key];
            }

            if ($result['error'] !== null) {
                $totals['errors'][] = "{$mailbox->address}: {$result['error']}";
            }
        }

        return Response::make(Response::text(sprintf(
            'Since %s: %d sent mails imported, %d skipped, %d leads created; then %d replies and %d bounces caught up.%s',
            $since->format('j M Y'), $totals['imported'], $totals['skipped'], $totals['created'], $totals['replies'], $totals['bounces'],
            $totals['errors'] === [] ? '' : ' Errors: '.implode('; ', $totals['errors']),
        )))->withStructuredContent($totals);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Import mail sent on or after this date, e.g. "2026-08-01".')->required(),
            'mailbox_id' => $schema->integer()->description('Only this mailbox. Default: every mailbox with a working login.'),
            'create_leads' => $schema->boolean()->description('Create a lead for recipients that are not leads yet.')->default(false),
            'niche_id' => $schema->integer()->description('The niche for leads created this way; required with create_leads.'),
        ];
    }
}
