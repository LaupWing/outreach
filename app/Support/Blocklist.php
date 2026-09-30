<?php

namespace App\Support;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Who asked not to be mailed. An address is blocked by itself; its domain too, unless
 * it is a mail provider shared by millions (blocking gmail.com would block everyone).
 */
class Blocklist
{
    private const array SHARED_DOMAINS = ['gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.nl', 'outlook.com', 'outlook.nl', 'live.nl', 'live.com', 'yahoo.com', 'icloud.com', 'me.com', 'ziggo.nl', 'kpnmail.nl', 'planet.nl', 'home.nl', 'xs4all.nl', 'telfort.nl', 'hetnet.nl', 'upcmail.nl', 'casema.nl', 'chello.nl', 'quicknet.nl', 'zeelandnet.nl'];

    public static function domainOf(?string $emailOrWebsite): ?string
    {
        if ($emailOrWebsite === null || trim($emailOrWebsite) === '') {
            return null;
        }

        $value = strtolower(trim($emailOrWebsite));
        $domain = str_contains($value, '@')
            ? Str::after($value, '@')
            : (string) preg_replace('#^https?://(www\.)?|/.*$#', '', $value);

        return $domain === '' || in_array($domain, self::SHARED_DOMAINS, true) ? null : $domain;
    }

    /**
     * Whether a lead (by its address or its website's domain) may not be mailed.
     */
    public static function blocks(User $user, Lead $lead): bool
    {
        $email = $lead->email === null ? null : strtolower(trim($lead->email));
        $domains = array_filter([self::domainOf($lead->email), self::domainOf($lead->website)]);

        if ($email === null && $domains === []) {
            return false;
        }

        return $user->blockedContacts()
            ->where(fn ($query) => $query
                ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
                ->when($domains !== [], fn ($query) => $query->orWhereIn('domain', $domains)))
            ->exists();
    }

    /**
     * Never mail this lead again: its address and domain go on the list, and it is
     * taken out of every sequence. Mail still waiting for it is cancelled.
     */
    public static function block(Lead $lead, ?string $reason = null): void
    {
        $user = $lead->user;

        if ($lead->email !== null) {
            $user->blockedContacts()->updateOrCreate(['email' => strtolower(trim($lead->email))], ['reason' => $reason]);
        }

        foreach (array_unique(array_filter([self::domainOf($lead->email), self::domainOf($lead->website)])) as $domain) {
            $user->blockedContacts()->updateOrCreate(['domain' => $domain], ['reason' => $reason]);
        }

        $lead->forceFill(['status' => LeadStatus::No, 'next_action_at' => null])->save();
        $lead->messages()->whereIn('status', ['queued', 'draft'])->delete();
    }

    /**
     * A new or freshly enriched lead that is on the list starts as "no".
     */
    public static function applyTo(Lead $lead): void
    {
        if ($lead->status !== LeadStatus::No && self::blocks($lead->user, $lead)) {
            $lead->forceFill(['status' => LeadStatus::No, 'next_action_at' => null])->save();
        }
    }

    /**
     * Words people use to ask to be taken off a list, in Dutch and English.
     */
    public static function asksToBeRemoved(string $text): bool
    {
        return preg_match('/uit (het|uw|je|jullie)? ?(mail|e-mail|adres)?(bestand|lijst)|afmelden|uitschrijven|niet (meer )?(mailen|benaderen|contacteren)|verwijder (mij|ons|me)|unsubscribe|remove (me|us)|do not (contact|email)/i', $text) === 1;
    }
}
