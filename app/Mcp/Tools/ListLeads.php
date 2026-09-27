<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStatus;
use App\Mcp\Account;
use App\Mcp\LeadSummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_leads')]
#[Description('List leads, newest first, optionally narrowed to a status, niche, scrape run, or only those with an email address. Returns at most 50 at a time.')]
#[IsReadOnly]
class ListLeads extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'niche_id' => ['sometimes', 'integer'],
            'run_id' => ['sometimes', 'integer'],
            'with_email' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $leads = $user->leads()
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['niche_id'] ?? null, fn ($query, int $niche) => $query->where('niche_id', $niche))
            ->when($validated['run_id'] ?? null, fn ($query, int $run) => $query->where('scrape_run_id', $run))
            ->when($validated['with_email'] ?? false, fn ($query) => $query->whereNotNull('email'))
            ->latest()
            ->orderByDesc('id')
            ->limit((int) ($validated['limit'] ?? 25))
            ->get();

        return Response::make(Response::text(sprintf('%d leads.', $leads->count())))
            ->withStructuredContent(['leads' => $leads->map(fn ($lead) => LeadSummary::from($lead))->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_map(fn (LeadStatus $status) => $status->value, LeadStatus::cases()))->description('Only leads in this status.'),
            'niche_id' => $schema->integer()->description('Only leads in this niche.'),
            'run_id' => $schema->integer()->description('Only leads found by this scrape run.'),
            'with_email' => $schema->boolean()->description('Only leads that have an email address.'),
            'limit' => $schema->integer()->description('How many, 1 to 50.')->default(25),
        ];
    }
}
