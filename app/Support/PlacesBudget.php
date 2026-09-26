<?php

namespace App\Support;

use App\Models\ScrapeRun;

/**
 * The free monthly volume of the Google Places SKU we call. One request is one
 * page of twenty results; the budget resets on the first of the month.
 */
class PlacesBudget
{
    /**
     * @return array{sku: string, used: int, free_limit: int, price_per_1000: int, resets_at: string}
     */
    public static function current(): array
    {
        return [
            'sku' => 'Text Search Enterprise',
            'used' => (int) ScrapeRun::query()
                ->where('started_at', '>=', now()->startOfMonth())
                ->sum('requests'),
            'free_limit' => (int) config('services.google.places.free_requests'),
            'price_per_1000' => (int) config('services.google.places.price_per_1000'),
            'resets_at' => now()->addMonthNoOverflow()->startOfMonth()->toJSON(),
        ];
    }

    /**
     * Requests still free this month.
     */
    public static function left(): int
    {
        $usage = self::current();

        return max($usage['free_limit'] - $usage['used'], 0);
    }
}
