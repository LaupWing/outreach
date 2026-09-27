<?php

namespace App\Mcp;

use App\Models\Lead;

/**
 * The shape of a lead in tool responses: what Claude needs to write a hook, nothing more.
 */
class LeadSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'company' => $lead->company,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'website' => $lead->website,
            'city' => $lead->city,
            'status' => $lead->status->value,
            'source' => $lead->source->value,
            'niche_id' => $lead->niche_id,
            'offer_id' => $lead->offer_id,
            'scrape_run_id' => $lead->scrape_run_id,
            'hook' => $lead->hook,
            'signals' => $lead->signals,
            'last_contact_at' => $lead->last_contact_at?->toJSON(),
            'next_action_at' => $lead->next_action_at?->toJSON(),
            // Where the lead's panel opens in the app.
            'url' => rtrim(config('app.url'), '/').'/leads?lead='.$lead->id,
        ];
    }
}
