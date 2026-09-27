<?php

namespace App\Mcp\Tools;

use App\Enums\NicheStatus;
use App\Mcp\Account;
use App\Mcp\Resources\CatalogApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_niche')]
#[Description('Create or change a niche: by id, or by name (created when new). Set the status (idea, testing, proven, dropped), why it was picked, or what was learned. Only the fields given are touched.')]
#[IsIdempotent]
#[RendersApp(resource: CatalogApp::class)]
class UpdateNiche extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'niche_id' => ['required_without:name', 'integer'],
            'name' => ['required_without:niche_id', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(NicheStatus::class)],
            'why' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'findings' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $niche = isset($validated['niche_id'])
            ? $user->niches()->find($validated['niche_id'])
            : $user->niches()->firstOrCreate(['name' => trim($validated['name'])], ['status' => NicheStatus::Idea]);

        if ($niche === null) {
            return Response::error("No niche with id {$validated['niche_id']} on this account.");
        }

        $created = $niche->wasRecentlyCreated;

        $niche->fill(collect($validated)->only(['status', 'why', 'findings'])->all())->save();

        return Response::make(Response::text(sprintf('%s "%s" (%s).', $created ? 'Created niche' : 'Updated niche', $niche->name, $niche->status->value)))
            ->withStructuredContent([
                'id' => $niche->id,
                'name' => $niche->name,
                'status' => $niche->status->value,
                'why' => $niche->why,
                'findings' => $niche->findings,
                'url' => rtrim(config('app.url'), '/').'/niches',
            ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'niche_id' => $schema->integer()->description('The niche to change. Leave out to create or find one by name.'),
            'name' => $schema->string()->description('Niche name, e.g. "Tandartsen". Created when it does not exist.'),
            'status' => $schema->string()->enum(array_map(fn (NicheStatus $status) => $status->value, NicheStatus::cases()))->description('idea → testing → proven or dropped.'),
            'why' => $schema->string()->description('Why this niche looks worth it.'),
            'findings' => $schema->string()->description('What the outreach taught you about it.'),
        ];
    }
}
