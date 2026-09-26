<?php

namespace App\Support\Places;

/**
 * One page of a Google Places text search. Bound to a fake in tests.
 */
interface PlacesSearch
{
    /**
     * @throws PlacesException when Google refuses the request
     */
    public function search(string $apiKey, string $query, string $place, ?string $pageToken = null): PlacesPage;
}
