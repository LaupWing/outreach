<?php

namespace App\Support\Mail;

use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * The {{tags}} in a sequence step. A tag is a lead field or a lead fact with that
 * name; the app knows nothing else about it. The AI (or a person) fills the facts.
 */
class Placeholders
{
    /**
     * Tags every lead has by itself; nobody has to explain or fill these.
     *
     * @var list<string>
     */
    public const BUILT_IN = ['company', 'email', 'phone', 'website', 'city', 'hook', 'hook_subject'];

    /**
     * Tag names as they appear in the text, in order, without duplicates.
     *
     * @return list<string>
     */
    public static function tagsIn(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * What the lead has for each tag: its own fields first, then its facts.
     *
     * @return array<string, string>
     */
    public static function valuesFor(Lead $lead): array
    {
        $hookSubject = $lead->hook === null
            ? "Jullie website, {$lead->company}"
            : Str::limit(rtrim($lead->hook, '.'), 60, '');

        $fields = array_filter([
            'company' => $lead->company,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'website' => $lead->website,
            'city' => $lead->city,
            'hook' => $lead->hook,
            'hook_subject' => $hookSubject,
        ], fn (?string $value) => $value !== null && $value !== '');

        return [...$fields, ...array_map('strval', $lead->facts ?? [])];
    }

    /**
     * Tags in the text the lead has no value for.
     *
     * @return list<string>
     */
    public static function missing(string $text, Lead $lead): array
    {
        $values = self::valuesFor($lead);

        return array_values(array_filter(self::tagsIn($text), fn (string $tag) => ! isset($values[$tag])));
    }

    /**
     * The text with every known tag replaced. Unknown tags stay as they are, so
     * they are visible instead of silently blank.
     */
    public static function fill(string $text, Lead $lead): string
    {
        $values = self::valuesFor($lead);

        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn (array $match) => $values[strtolower($match[1])] ?? $match[0],
            $text,
        );
    }
}
