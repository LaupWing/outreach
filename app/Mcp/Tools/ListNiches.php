<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStatus;
use App\Mcp\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_niches')]
#[Description('The niches (kinds of business) the account works, with status (idea, testing, proven, dropped), why it was picked, what was learned, and lead counts.')]
#[IsReadOnly]
class ListNiches extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = Account::for($request);

        $niches = $user->niches()
            ->withCount([
                'leads',
                'leads as with_email_count' => fn ($query) => $query->whereNotNull('email'),
                'leads as replied_count' => fn ($query) => $query->whereIn('status', [LeadStatus::Replied, LeadStatus::Customer]),
                'leads as customers_count' => fn ($query) => $query->where('status', LeadStatus::Customer),
                'offers',
            ])
            ->orderBy('name')
            ->get();

        return Response::make(Response::text(sprintf('%d niches.', $niches->count())))
            ->withStructuredContent(['niches' => $niches->map(fn ($niche) => [
                'id' => $niche->id,
                'name' => $niche->name,
                'status' => $niche->status->value,
                'why' => $niche->why,
                'findings' => $niche->findings,
                'leads' => $niche->leads_count,
                'with_email' => $niche->with_email_count,
                'replied' => $niche->replied_count,
                'customers' => $niche->customers_count,
                'offers' => $niche->offers_count,
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
