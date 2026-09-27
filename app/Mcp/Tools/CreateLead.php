<?php

namespace App\Mcp\Tools;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\NicheStatus;
use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Mcp\Resources\LeadCardApp;
use App\Models\Offer;
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

#[Name('create_lead')]
#[Description('Add a lead by hand: company, niche (created when new), and whatever else is known (email, phone, website, city, hook, facts, offer, status). A lead with the same email or website on this account is returned instead of duplicated.')]
#[RendersApp(resource: LeadCardApp::class)]
class CreateLead extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'niche' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hook' => ['sometimes', 'nullable', 'string', 'max:500'],
            'facts' => ['sometimes', 'array'],
            'facts.*' => ['nullable', 'string', 'max:2000'],
            'offer_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
        ]);

        $website = isset($validated['website']) ? strtolower(preg_replace('#^https?://(www\.)?|/.*$#', '', trim($validated['website'])) ?? '') : null;
        $email = isset($validated['email']) ? strtolower(trim($validated['email'])) : null;

        $existing = $user->leads()
            ->where(fn ($query) => $query
                ->when($email, fn ($query) => $query->orWhere('email', $email))
                ->when($website, fn ($query) => $query->orWhere('website', $website)))
            ->first();

        if (($email !== null || $website !== null) && $existing !== null) {
            return Response::make(Response::text("{$existing->company} already exists (id {$existing->id}); nothing added."))
                ->withStructuredContent(['lead' => [...LeadSummary::from($existing), 'facts' => $existing->facts]]);
        }

        $niche = $user->niches()->firstOrCreate(['name' => trim($validated['niche'])], ['status' => NicheStatus::Idea]);

        $facts = array_filter(array_map(fn ($value) => trim((string) $value), $validated['facts'] ?? []), fn (string $value) => $value !== '');

        $lead = $user->leads()->create([
            'niche_id' => $niche->id,
            'company' => trim($validated['company']),
            'email' => $email ?: null,
            'phone' => $validated['phone'] ?? null,
            'website' => $website ?: null,
            'city' => $validated['city'] ?? null,
            'hook' => $validated['hook'] ?? null,
            'facts' => $facts === [] ? null : $facts,
            'offer_id' => $validated['offer_id'] ?? null,
            'status' => $validated['status'] ?? LeadStatus::New,
            'source' => LeadSource::Manual,
        ]);

        return Response::make(Response::text("Added {$lead->company} (id {$lead->id}) to {$niche->name}."))
            ->withStructuredContent(['lead' => [...LeadSummary::from($lead), 'facts' => $lead->facts]]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'company' => $schema->string()->required(),
            'niche' => $schema->string()->description('Niche name; created when new.')->required(),
            'email' => $schema->string(),
            'phone' => $schema->string(),
            'website' => $schema->string()->description('Domain, e.g. "tandartsbos.nl".'),
            'city' => $schema->string(),
            'hook' => $schema->string()->description('One sentence about their site that opens the first mail.'),
            'facts' => $schema->object()->description('Tag values, e.g. {"first_name": "Marieke"}.'),
            'offer_id' => $schema->integer()->description('The offer to work this lead with.'),
            'status' => $schema->string()->enum(array_map(fn (LeadStatus $status) => $status->value, LeadStatus::cases()))->description('Default: new.'),
        ];
    }
}
