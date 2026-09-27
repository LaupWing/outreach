<?php

namespace App\Mcp\Tools;

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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_offer')]
#[Description('Change an offer: name, description, status, auto follow-up, tag explanations (merged), or replace its whole sequence with steps. Only the fields given are touched.')]
#[IsIdempotent]
#[RendersApp(resource: CatalogApp::class)]
class UpdateOffer extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjects($request->all(), ['steps', 'placeholders']));

        $validated = $request->validate([
            'offer_id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(OfferStatus::class)],
            'auto_follow_up' => ['sometimes', 'boolean'],
            ...Offers::sequenceRules(),
        ]);

        $offer = $user->offers()->with('steps')->find($validated['offer_id']);

        if ($offer === null) {
            return Response::error("No offer with id {$validated['offer_id']} on this account.");
        }

        $explanations = [...($offer->placeholders ?? []), ...array_filter($validated['placeholders'] ?? [], fn ($value) => is_string($value) && trim($value) !== '')];
        $steps = $validated['steps'] ?? $offer->steps->map(fn ($step) => ['subject' => $step->subject, 'body' => $step->body])->all();
        $missing = Offers::unexplained($steps, $explanations);

        if ($missing !== []) {
            return Response::error('Explain these custom tags in placeholders first: {{'.implode('}}, {{', $missing).'}}.');
        }

        DB::transaction(function () use ($offer, $validated, $explanations): void {
            $offer->fill(collect($validated)->only(['name', 'description', 'status', 'auto_follow_up'])->all());
            $offer->placeholders = $explanations === [] ? null : $explanations;
            $offer->save();

            if (isset($validated['steps'])) {
                Offers::writeSteps($offer, $validated['steps']);
            }
        });

        return Response::make(Response::text(sprintf('Updated offer "%s".', $offer->name)))
            ->withStructuredContent(['offers' => [Offers::from($offer->refresh())], 'url' => rtrim(config('app.url'), '/').'/offers']);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'offer_id' => $schema->integer()->description('The offer to change.')->required(),
            'name' => $schema->string(),
            'description' => $schema->string(),
            'status' => $schema->string()->enum(array_map(fn (OfferStatus $status) => $status->value, OfferStatus::cases())),
            'auto_follow_up' => $schema->boolean(),
            'steps' => $schema->array()->description('Replaces the whole sequence: [{subject, body, days_after_previous}].'),
            'placeholders' => $schema->object()->description('Tag explanations to merge in.'),
        ];
    }
}
