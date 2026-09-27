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

#[Name('scrape_status')]
#[Description('How a scrape run is doing: status, businesses found, leads enriched so far, with email, blocked, and the error when it failed.')]
#[IsReadOnly]
class ScrapeStatus extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate(['run_id' => ['required', 'integer']]);

        $run = $user->scrapeRuns()->find($validated['run_id']);

        if ($run === null) {
            return Response::error("No scrape run with id {$validated['run_id']} on this account.");
        }

        $leads = $run->leads()->count();
        $enriched = $run->leads()->whereNotNull('signals')->count();

        return Reply::make(sprintf(
            'Run %d (%s in %s): %s. %d found, %d leads, %d enriched, %d with email, %d blocked.%s',
            $run->id, $run->query, $run->place, $run->status->value, $run->found, $leads, $enriched, $run->with_email, $run->blocked,
            $run->error === null ? '' : ' Error: '.$run->error,
        ), [
            'run_id' => $run->id,
            'status' => $run->status->value,
            'query' => $run->query,
            'place' => $run->place,
            'found' => $run->found,
            'leads' => $leads,
            'enriched' => $enriched,
            'with_email' => $run->with_email,
            'blocked' => $run->blocked,
            'requests' => $run->requests,
            'error' => $run->error,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->integer()->description('The scrape run to look at.')->required(),
        ];
    }
}
