<?php

namespace App\Mcp\Tools;

use App\Enums\NicheStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\Reply;
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

#[Name('update_niches')]
#[Description('Create or change niches, one or many: per niche either niche_id or name (created when new), plus status (idea, testing, proven, dropped), why it was picked, or what was learned. Only the fields given are touched.')]
#[IsIdempotent]
#[RendersApp(resource: CatalogApp::class)]
class UpdateNiches extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjects($request->all(), ['niches']));

        $validated = $request->validate([
            'niches' => ['required', 'array', 'min:1', 'max:100'],
            'niches.*.niche_id' => ['required_without:niches.*.name', 'integer'],
            'niches.*.name' => ['required_without:niches.*.niche_id', 'string', 'max:255'],
            'niches.*.status' => ['sometimes', Rule::enum(NicheStatus::class)],
            'niches.*.why' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'niches.*.findings' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $done = [];
        $skipped = [];
        $created = 0;

        foreach ($validated['niches'] as $row) {
            $niche = isset($row['niche_id'])
                ? $user->niches()->find($row['niche_id'])
                : $user->niches()->firstOrCreate(['name' => trim($row['name'])], ['status' => NicheStatus::Idea]);

            if ($niche === null) {
                $skipped[] = "#{$row['niche_id']}: not on this account";

                continue;
            }

            $created += $niche->wasRecentlyCreated ? 1 : 0;
            $niche->fill(collect($row)->only(['status', 'why', 'findings'])->all())->save();

            $done[] = ['id' => $niche->id, 'name' => $niche->name, 'status' => $niche->status->value, 'why' => $niche->why, 'findings' => $niche->findings];
        }

        $text = sprintf('%d niches saved (%d new).', count($done), $created);

        if ($skipped !== []) {
            $text .= ' Skipped: '.implode('; ', $skipped).'.';
        }

        return Reply::make($text, ['niches' => $done, 'skipped' => $skipped, 'url' => rtrim(config('app.url'), '/').'/niches']);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'niches' => $schema->array()->description('[{niche_id? | name, status?, why?, findings?}]')->required(),
        ];
    }
}
