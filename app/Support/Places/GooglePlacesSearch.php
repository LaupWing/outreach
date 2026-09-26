<?php

namespace App\Support\Places;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Places API (New) text search. One call is one page of twenty and one request
 * against the monthly budget. The field mask keeps the call in the Enterprise
 * SKU: website and phone are the priciest fields we need, nothing beyond them.
 */
class GooglePlacesSearch implements PlacesSearch
{
    private const string ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    private const string FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.addressComponents,places.websiteUri,places.internationalPhoneNumber,nextPageToken';

    public function search(string $apiKey, string $query, string $place, ?string $pageToken = null): PlacesPage
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ])
                ->post(self::ENDPOINT, array_filter([
                    'textQuery' => "{$query} in {$place}",
                    'pageSize' => 20,
                    'languageCode' => 'nl',
                    'regionCode' => 'NL',
                    'pageToken' => $pageToken,
                ]));
        } catch (ConnectionException $exception) {
            throw new PlacesException('Google Places could not be reached: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new PlacesException(sprintf(
                'Google Places refused the search (%d): %s',
                $response->status(),
                $response->json('error.message', 'no details'),
            ));
        }

        $places = collect($response->json('places', []))
            ->map(fn (array $item): PlaceResult => new PlaceResult(
                placeId: $item['id'],
                name: $item['displayName']['text'] ?? 'Unknown',
                address: $item['formattedAddress'] ?? null,
                city: $this->city($item['addressComponents'] ?? []),
                website: $item['websiteUri'] ?? null,
                phone: $item['internationalPhoneNumber'] ?? null,
            ))
            ->values()
            ->all();

        return new PlacesPage($places, $response->json('nextPageToken'));
    }

    /**
     * @param  list<array{types?: list<string>, longText?: string}>  $components
     */
    private function city(array $components): ?string
    {
        foreach ($components as $component) {
            if (in_array('locality', $component['types'] ?? [], true)) {
                return $component['longText'] ?? null;
            }
        }

        return null;
    }
}
