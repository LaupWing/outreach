<?php

namespace App\Mcp\Tools;

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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_offers')]
#[Description('Change offers, one or many: per offer offer_id plus name, description, status, auto_follow_up, tag explanations (merged), or steps to replace its whole sequence. Only the fields given are touched.')]
#[IsIdempotent]
#[RendersApp(resource: CatalogApp::class)]
class UpdateOffers extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjectsIn($request->all(), 'offers', ['steps', 'placeholders']));

        $validated = $request->validate([
            'offers' => ['required', 'array', 'min:1', 'max:50'],
            'offers.*.offer_id' => ['required', 'integer'],
            'offers.*.name' => ['sometimes', 'string', 'max:255'],
            'offers.*.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'offers.*.status' => ['sometimes', Rule::enum(OfferStatus::class)],
            'offers.*.auto_follow_up' => ['sometimes', 'boolean'],
            ...Offers::sequenceRules('offers.*.'),
        ]);

        $done = [];
        $skipped = [];

        foreach ($validated['offers'] as $row) {
            $offer = $user->offers()->with('steps')->find($row['offer_id']);

            if ($offer === null) {
                $skipped[] = "#{$row['offer_id']}: not on this account";

                continue;
            }

            $explanations = [...($offer->placeholders ?? []), ...array_filter($row['placeholders'] ?? [], fn ($value) => is_string($value) && trim($value) !== '')];
            $steps = $row['steps'] ?? $offer->steps->map(fn ($step) => ['subject' => $step->subject, 'body' => $step->body])->all();
            $missing = Offers::unexplained($steps, $explanations);

            if ($missing !== []) {
                $skipped[] = "{$offer->name}: explain {{".implode('}}, {{', $missing).'}} in placeholders';

                continue;
            }

            DB::transaction(function () use ($offer, $row, $explanations): void {
                $offer->fill(collect($row)->only(['name', 'description', 'status', 'auto_follow_up'])->all());
                $offer->placeholders = $explanations === [] ? null : $explanations;
                $offer->save();

                if (isset($row['steps'])) {
                    Offers::writeSteps($offer, $row['steps']);
                }
            });

            $done[] = Offers::from($offer->refresh());
        }

        $text = sprintf('%d offers updated.', count($done));

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
            'offers' => $schema->array()->description('[{offer_id, name?, description?, status?, auto_follow_up?, steps?: [{subject, body, days_after_previous}], placeholders?: {tag: explanation}}]')->required(),
        ];
    }
}
