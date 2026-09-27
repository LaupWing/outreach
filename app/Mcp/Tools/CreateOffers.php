<?php

namespace App\Mcp\Tools;

use App\Enums\NicheStatus;
use App\Enums\OfferStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\Offers;
use App\Mcp\Reply;
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

#[Name('create_offers')]
#[Description('Create offers (what is pitched to a niche), one or many, each with its mail sequence: steps in order, first mail first, each with subject, body and days_after_previous. Bodies may use {{tags}}: built-ins (company, city, email, phone, website, hook, hook_subject) and custom ones like {{compliment}}; every custom tag needs an explanation in placeholders so the AI knows what to write per lead. An offer with an unexplained tag is skipped and reported.')]
#[RendersApp(resource: CatalogApp::class)]
class CreateOffers extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjectsIn($request->all(), 'offers', ['steps', 'placeholders']));

        $validated = $request->validate([
            'offers' => ['required', 'array', 'min:1', 'max:50'],
            'offers.*.name' => ['required', 'string', 'max:255'],
            'offers.*.niche' => ['required', 'string', 'max:255'],
            'offers.*.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'offers.*.status' => ['sometimes', Rule::enum(OfferStatus::class)],
            'offers.*.auto_follow_up' => ['sometimes', 'boolean'],
            ...Offers::sequenceRules('offers.*.'),
        ]);

        $done = [];
        $skipped = [];

        foreach ($validated['offers'] as $row) {
            $steps = $row['steps'] ?? [];
            $explanations = array_filter($row['placeholders'] ?? [], fn ($value) => is_string($value) && trim($value) !== '');
            $missing = Offers::unexplained($steps, $explanations);

            if ($missing !== []) {
                $skipped[] = "{$row['name']}: explain {{".implode('}}, {{', $missing).'}} in placeholders';

                continue;
            }

            $offer = DB::transaction(function () use ($user, $row, $steps, $explanations) {
                $niche = $user->niches()->firstOrCreate(['name' => trim($row['niche'])], ['status' => NicheStatus::Idea]);

                $offer = $user->offers()->create([
                    'niche_id' => $niche->id,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'status' => $row['status'] ?? ($steps === [] ? OfferStatus::Idea : OfferStatus::Active),
                    'auto_follow_up' => $row['auto_follow_up'] ?? true,
                    'placeholders' => $explanations === [] ? null : $explanations,
                ]);

                Offers::writeSteps($offer, $steps);

                return $offer;
            });

            $done[] = Offers::from($offer);
        }

        $text = sprintf('%d offers created.', count($done));

        if ($skipped !== []) {
            $text .= ' Skipped: '.implode('; ', $skipped).'.';
        }

        return Reply::make($text, ['offers' => $done, 'skipped' => $skipped, 'url' => rtrim(config('app.url'), '/').'/offers']);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'offers' => $schema->array()->description('[{name, niche, description?, status?, auto_follow_up?, steps: [{subject, body, days_after_previous}], placeholders: {tag: explanation}}]')->required(),
        ];
    }
}
