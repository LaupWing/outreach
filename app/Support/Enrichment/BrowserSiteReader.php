<?php

namespace App\Support\Enrichment;

use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Renders a page in headless Chrome and returns the HTML after JavaScript ran.
 * Slow (a few seconds) and heavy, so only the fallback for sites the plain fetch
 * cannot read. Needs Chromium on the server; see config('services.browser').
 */
class BrowserSiteReader implements SiteReader
{
    public function fetch(string $url): ?string
    {
        try {
            $shot = Browsershot::url($url)
                ->userAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36')
                ->timeout(25)
                ->waitUntilNetworkIdle(strict: false)
                ->noSandbox()
                ->setOption('args', ['--disable-gpu', '--disable-dev-shm-usage']);

            if (($chrome = config('services.browser.chrome_path')) !== null) {
                $shot->setChromePath($chrome);
            }

            if (($node = config('services.browser.node_binary')) !== null) {
                $shot->setNodeBinary($node);
            }

            return $shot->bodyHtml();
        } catch (Throwable) {
            return null;
        }
    }
}
