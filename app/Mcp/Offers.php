<?php

namespace App\Mcp;

use App\Models\Offer;
use App\Support\Mail\Placeholders;

/**
 * The shape of an offer in tool responses, and the rules for writing its sequence.
 */
class Offers
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Offer $offer): array
    {
        $offer->loadMissing(['niche', 'steps' => fn ($query) => $query->orderBy('step')]);

        return [
            'id' => $offer->id,
            'name' => $offer->name,
            'niche' => $offer->niche?->name,
            'niche_id' => $offer->niche_id,
            'status' => $offer->status->value,
            'description' => $offer->description,
            'auto_follow_up' => $offer->auto_follow_up,
            'tag_explanations' => $offer->placeholders,
            'steps' => $offer->steps->map(fn ($step) => [
                'step' => $step->step,
                'days_after_previous' => $step->days_after_previous,
                'subject' => $step->subject,
                'body' => $step->body,
                'tags' => Placeholders::tagsIn($step->subject.' '.$step->body),
            ])->all(),
            'url' => rtrim(config('app.url'), '/').'/offers',
        ];
    }

    /**
     * Validation rules for a sequence and its tag explanations, shared by create and update.
     *
     * @return array<string, array<int, string>>
     */
    public static function sequenceRules(): array
    {
        return [
            'steps' => ['sometimes', 'array', 'max:20'],
            'steps.*.subject' => ['required', 'string', 'max:255'],
            'steps.*.body' => ['required', 'string', 'max:10000'],
            'steps.*.days_after_previous' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'placeholders' => ['sometimes', 'array'],
            'placeholders.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Custom tags in the steps that have no explanation yet, on the offer or in the call.
     *
     * @param  list<array{subject: string, body: string}>  $steps
     * @param  array<string, string|null>  $explanations
     * @return list<string>
     */
    public static function unexplained(array $steps, array $explanations): array
    {
        $text = implode(' ', array_map(fn (array $step) => $step['subject'].' '.$step['body'], $steps));
        $known = array_keys(array_filter($explanations, fn ($value) => is_string($value) && trim($value) !== ''));

        return array_values(array_diff(Placeholders::tagsIn($text), Placeholders::BUILT_IN, $known));
    }

    /**
     * Replace the offer's sequence with the given steps, numbered from one.
     *
     * @param  list<array{subject: string, body: string, days_after_previous?: int}>  $steps
     */
    public static function writeSteps(Offer $offer, array $steps): void
    {
        $offer->steps()->delete();

        foreach (array_values($steps) as $index => $step) {
            $offer->steps()->create([
                'user_id' => $offer->user_id,
                'step' => $index + 1,
                'days_after_previous' => $index === 0 ? 0 : ($step['days_after_previous'] ?? 3),
                'subject' => $step['subject'],
                'body' => $step['body'],
            ]);
        }
    }
}
