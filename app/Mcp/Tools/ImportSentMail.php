<?php

namespace App\Mcp\Tools;

use App\Jobs\ImportSentMailJob;
use App\Mcp\Account;
use App\Mcp\Reply;
use App\Models\Niche;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
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
#[Description('Bring mail that was sent by hand, before Snelreach, into the leads\' threads: reads the mailbox\'s sent folder since a date, attaches each mail to the lead with that address (or creates the lead when create_leads is set), then catches up on replies and bounces since that date. Runs in the background: call again with status=true to see the outcome. Safe to run again; known mails are skipped.')]
#[IsOpenWorld]
#[IsIdempotent]
class ImportSentMail extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'status' => ['sometimes', 'boolean'],
            'since' => ['required_unless:status,true', 'date'],
            'mailbox_id' => ['sometimes', 'integer'],
            'create_leads' => ['sometimes', 'boolean'],
            'niche_id' => ['required_if:create_leads,true', 'integer', Rule::exists(Niche::class, 'id')->where('user_id', $user->id)],
        ]);

        if ($validated['status'] ?? false) {
            $status = Cache::get(ImportSentMailJob::statusKey($user->id));

            if ($status === null) {
                return Response::text('No import has been started yet.');
            }

            $totals = ['imported' => 0, 'skipped_known' => 0, 'skipped_no_lead' => 0, 'created' => 0, 'replies' => 0, 'bounces' => 0];
            $noLead = [];
            $errors = [];

            foreach ($status['mailboxes'] as $address => $result) {
                foreach ($totals as $key => $total) {
                    $totals[$key] += $result[$key] ?? 0;
                }

                $noLead = [...$noLead, ...($result['no_lead_addresses'] ?? [])];

                if (($result['error'] ?? null) !== null) {
                    $errors[] = "{$address}: {$result['error']}";
                }
            }

            return Reply::make(sprintf(
                'Import %s (%d of %d mailboxes done): %d sent mails imported, %d skipped because they are already in a thread, %d skipped because no lead has that address, %d leads created; %d replies and %d bounces caught up.%s%s',
                $status['state'], count($status['mailboxes']), $status['expected'] ?? 1,
                $totals['imported'], $totals['skipped_known'], $totals['skipped_no_lead'], $totals['created'], $totals['replies'], $totals['bounces'],
                $noLead === [] ? '' : ' Addresses without a lead: '.implode(', ', array_slice(array_values(array_unique($noLead)), 0, 50)).'.',
                $errors === [] ? '' : ' Errors: '.implode('; ', $errors),
            ), [...$status, 'totals' => $totals, 'no_lead_addresses' => array_values(array_unique($noLead))]);
        }

        $mailboxes = $user->mailboxes()
            ->when(isset($validated['mailbox_id']), fn ($query) => $query->whereKey($validated['mailbox_id']))
            ->get();

        if ($mailboxes->isEmpty()) {
            return Response::error('No mailbox with a working login to read from.');
        }

        $since = CarbonImmutable::parse($validated['since']);

        Cache::put(ImportSentMailJob::statusKey($user->id), [
            'state' => 'running',
            'since' => $since->toDateString(),
            'expected' => $mailboxes->count(),
            'mailboxes' => [],
            'started_at' => now()->toJSON(),
        ], now()->addDay());

        foreach ($mailboxes as $mailbox) {
            ImportSentMailJob::dispatch($mailbox, $since, $validated['create_leads'] ?? false, $validated['niche_id'] ?? null);
        }

        return Reply::make(sprintf(
            'Import started for %d mailbox%s since %s. Reading Gmail takes a minute or two; call import_sent_mail with status=true to see the outcome.',
            $mailboxes->count(), $mailboxes->count() === 1 ? '' : 'es', $since->format('j M Y'),
        ), ['state' => 'running', 'mailboxes' => $mailboxes->pluck('address')->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Import mail sent on or after this date, e.g. "2026-08-01". Not needed with status=true.'),
            'status' => $schema->boolean()->description('Report how the last import went instead of starting one.')->default(false),
            'mailbox_id' => $schema->integer()->description('Only this mailbox. Default: every mailbox with a working login.'),
            'create_leads' => $schema->boolean()->description('Create a lead for recipients that are not leads yet.')->default(false),
            'niche_id' => $schema->integer()->description('The niche for leads created this way; required with create_leads.'),
        ];
    }
}
