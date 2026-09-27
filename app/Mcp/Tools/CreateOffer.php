<?php

namespace App\Mcp\Tools;

use App\Enums\NicheStatus;
use App\Enums\OfferStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\Offers;
use App\Mcp\Resources\CatalogApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;

#[Name('create_offer')]
#[Description('Create an offer (what is pitched to a niche) with its mail sequence: steps in order, first mail first, each with subject, body and days_after_previous. Bodies may use {{tags}}: built-ins (company, city, email, phone, website, hook, hook_subject) and custom ones like {{compliment}}; every custom tag needs an explanation in placeholders so the AI knows what to write per lead.')]
#[RendersApp(resource: CatalogApp::class)]
class CreateOffer extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjects($request->all(), ['steps', 'placeholders']));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'niche' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(OfferStatus::class)],
            'auto_follow_up' => ['sometimes', 'boolean'],
            ...Offers::sequenceRules(),
        ]);

        $steps = $validated['steps'] ?? [];
        $explanations = array_filter($validated['placeholders'] ?? [], fn ($value) => is_string($value) && trim($value) !== '');
        $missing = Offers::unexplained($steps, $explanations);

        if ($missing !== []) {
            return Response::error('Explain these custom tags in placeholders first: {{'.implode('}}, {{', $missing).'}}.');
        }

        $offer = DB::transaction(function () use ($user, $validated, $steps, $explanations) {
            $niche = $user->niches()->firstOrCreate(['name' => trim($validated['niche'])], ['status' => NicheStatus::Idea]);

            $offer = $user->offers()->create([
                'niche_id' => $niche->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? ($steps === [] ? OfferStatus::Idea : OfferStatus::Active),
                'auto_follow_up' => $validated['auto_follow_up'] ?? true,
                'placeholders' => $explanations === [] ? null : $explanations,
            ]);

            Offers::writeSteps($offer, $steps);

            return $offer;
        });

        return Response::make(Response::text(sprintf('Created offer "%s" for %s with %d steps.', $offer->name, $offer->niche->name, count($steps))))
            ->withStructuredContent(['offers' => [Offers::from($offer)], 'url' => rtrim(config('app.url'), '/').'/offers']);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Offer name, e.g. "Nieuwe website in 2 weken".')->required(),
            'niche' => $schema->string()->description('Niche name; created when new.')->required(),
            'description' => $schema->string()->description('What is pitched, in a sentence or two.'),
            'status' => $schema->string()->enum(array_map(fn (OfferStatus $status) => $status->value, OfferStatus::cases()))->description('Default: active with steps, idea without.'),
            'auto_follow_up' => $schema->boolean()->description('Send due follow-ups automatically. Default true.')->default(true),
            'steps' => $schema->array()->description('The sequence, first mail first: [{subject, body, days_after_previous}]. Step 1 always has 0 days.'),
            'placeholders' => $schema->object()->description('Explanation per custom tag, e.g. {"compliment": "One honest sentence about their site."}.'),
        ];
    }
}
