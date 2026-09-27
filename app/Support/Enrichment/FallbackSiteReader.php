<?php

namespace App\Support\Enrichment;

/**
 * The plain fetch first; when that fails or only returns an app shell that renders
 * with JavaScript, the browser has a go. Sites that simply block us stay blocked.
 */
class FallbackSiteReader implements SiteReader
{
    public function __construct(private SiteReader $plain, private SiteReader $browser) {}

    public function fetch(string $url): ?string
    {
        $html = $this->plain->fetch($url);

        if ($html !== null && ! self::looksJavascriptOnly($html)) {
            return $html;
        }

        return $this->browser->fetch($url) ?? $html;
    }

    /**
     * Hardly any text but scripts: an app that draws itself in the browser.
     */
    public static function looksJavascriptOnly(string $html): bool
    {
        $text = trim(strip_tags(preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/is', '', $html) ?? ''));

        return strlen($text) < 200 && preg_match('/<script/i', $html) === 1;
    }
}
