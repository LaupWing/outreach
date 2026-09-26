<?php

namespace App\Support\Places;

/**
 * Up to twenty businesses and the token for the next page, if there is one.
 */
final class PlacesPage
{
    /**
     * @param  list<PlaceResult>  $places
     */
    public function __construct(
        public readonly array $places,
        public readonly ?string $nextPageToken,
    ) {}
}
