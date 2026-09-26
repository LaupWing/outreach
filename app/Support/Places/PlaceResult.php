<?php

namespace App\Support\Places;

/**
 * The few fields we read from a place. Asking for more moves the call to a
 * pricier SKU, so this list is deliberately short.
 */
final class PlaceResult
{
    public function __construct(
        public readonly string $placeId,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $website,
        public readonly ?string $phone,
    ) {}

    /**
     * The bare host of the website, the way leads store it: "tandartsdelinde.nl".
     */
    public function host(): ?string
    {
        if ($this->website === null) {
            return null;
        }

        $host = parse_url($this->website, PHP_URL_HOST) ?: $this->website;

        return strtolower(preg_replace('/^www\./i', '', $host));
    }
}
