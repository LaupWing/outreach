<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\Reply;
use App\Mcp\Resources\CatalogApp;
use App\Support\Mail\Placeholders;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_offers')]
#[Description('The offers (what is pitched, per niche) with their mail sequences: each step\'s subject, body and the {{tags}} it uses, plus what each custom tag should say.')]
#[IsReadOnly]
#[RendersApp(resource: CatalogApp::class)]
class ListOffers extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $offers = $user->offers()->with(['niche', 'steps' => fn ($query) => $query->orderBy('step')])->orderBy('name')->get();

        return Reply::make(sprintf('%d offers.', $offers->count()), ['url' => rtrim(config('app.url'), '/').'/offers', 'offers' => $offers->map(fn ($offer) => [
            'id' => $offer->id,
            'name' => $offer->name,
            'niche' => $offer->niche?->name,
            'niche_id' => $offer->niche_id,
            'status' => $offer->status->value,
            'description' => $offer->description,
            'auto_follow_up' => $offer->auto_follow_up,
            'tag_explanations' => $offer->placeholders,
            'steps' => $offer->steps->map(fn ($step) => [
                'step' => $step->step,
                'days_after_previous' => $step->days_after_previous,
                'subject' => $step->subject,
                'body' => $step->body,
                'tags' => Placeholders::tagsIn($step->subject.' '.$step->body),
            ])->all(),
        ])->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
