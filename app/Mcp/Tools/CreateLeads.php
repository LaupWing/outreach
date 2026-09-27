<?php

namespace App\Mcp\Tools;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\NicheStatus;
use App\Mcp\Account;
use App\Mcp\Arguments;
use App\Mcp\LeadSummary;
use App\Mcp\Resources\LeadListApp;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\User;
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

#[Name('create_leads')]
#[Description('Add leads by hand, one or many at once: each with company and niche (created when new), plus whatever else is known (email, phone, website, city, hook, facts, offer_id, status). A lead whose email or website already exists on this account is skipped, not duplicated. Returns the new leads as a list.')]
#[RendersApp(resource: LeadListApp::class)]
class CreateLeads extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $request->setArguments(Arguments::decodeObjectsIn($request->all(), 'leads', ['facts']));

        $validated = $request->validate([
            'niche' => ['sometimes', 'string', 'max:255'],
            'offer_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'leads' => ['required', 'array', 'min:1', 'max:200'],
            'leads.*.company' => ['required', 'string', 'max:255'],
            'leads.*.niche' => ['required_without:niche', 'string', 'max:255'],
            'leads.*.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'leads.*.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'leads.*.website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'leads.*.city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'leads.*.hook' => ['sometimes', 'nullable', 'string', 'max:500'],
            'leads.*.facts' => ['sometimes', 'array'],
            'leads.*.facts.*' => ['nullable', 'string', 'max:2000'],
            'leads.*.offer_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $user->id)],
            'leads.*.status' => ['sometimes', Rule::enum(LeadStatus::class)],
        ]);

        $created = [];
        $skipped = [];

        foreach ($validated['leads'] as $row) {
            $lead = $this->create($user, $row, $validated['niche'] ?? null, $validated['offer_id'] ?? null);

            if ($lead === null) {
                $skipped[] = $row['company'];
            } else {
                $created[] = $lead;
            }
        }

        $text = sprintf('%d leads added.', count($created));

        if ($skipped !== []) {
            $text .= ' Already existed (skipped): '.implode(', ', $skipped).'.';
        }

        return Response::make(Response::text($text))->withStructuredContent([
            'leads' => array_map(fn ($lead) => [...LeadSummary::from($lead), 'facts' => $lead->facts], $created),
            'skipped' => $skipped,
            'url' => rtrim(config('app.url'), '/').'/leads',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function create(User $user, array $row, ?string $defaultNiche, ?int $defaultOffer): ?Lead
    {
        $website = isset($row['website']) && $row['website'] !== null
            ? strtolower(preg_replace('#^https?://(www\.)?|/.*$#', '', trim($row['website'])) ?? '')
            : null;
        $email = isset($row['email']) && $row['email'] !== null ? strtolower(trim($row['email'])) : null;

        if ($email !== null || $website !== null) {
            $exists = $user->leads()
                ->where(fn ($query) => $query
                    ->when($email, fn ($query) => $query->orWhere('email', $email))
                    ->when($website, fn ($query) => $query->orWhere('website', $website)))
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $niche = $user->niches()->firstOrCreate(['name' => trim($row['niche'] ?? $defaultNiche)], ['status' => NicheStatus::Idea]);

        $facts = array_filter(array_map(fn ($value) => trim((string) $value), $row['facts'] ?? []), fn (string $value) => $value !== '');

        return $user->leads()->create([
            'niche_id' => $niche->id,
            'company' => trim($row['company']),
            'email' => $email ?: null,
            'phone' => $row['phone'] ?? null,
            'website' => $website ?: null,
            'city' => $row['city'] ?? null,
            'hook' => $row['hook'] ?? null,
            'facts' => $facts === [] ? null : $facts,
            'offer_id' => $row['offer_id'] ?? $defaultOffer,
            'status' => $row['status'] ?? LeadStatus::New,
            'source' => LeadSource::Manual,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leads' => $schema->array()->description('The leads: [{company, niche?, email?, phone?, website?, city?, hook?, facts?, offer_id?, status?}].')->required(),
            'niche' => $schema->string()->description('Default niche for every lead that gives none; created when new.'),
            'offer_id' => $schema->integer()->description('Default offer for every lead that gives none.'),
        ];
    }
}
