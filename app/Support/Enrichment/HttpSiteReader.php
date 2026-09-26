<?php

namespace App\Support\Enrichment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * A plain browser-like GET with a short timeout. Anything but a 2xx HTML page
 * counts as unreadable; the enricher decides what that means for the lead.
 */
class HttpSiteReader implements SiteReader
{
    public function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36')
                ->accept('text/html')
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful() || ! str_contains($response->header('Content-Type'), 'html')) {
            return null;
        }

        return $response->body();
    }
}
