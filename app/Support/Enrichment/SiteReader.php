<?php

namespace App\Support\Enrichment;

/**
 * Fetches a page of a lead's website. Bound to a fake in tests.
 */
interface SiteReader
{
    /**
     * The HTML of the page, or null when the site blocks us, is down, or times out.
     */
    public function fetch(string $url): ?string;
}
