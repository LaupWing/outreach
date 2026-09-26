<?php

namespace App\Support\Enrichment;

use App\Models\Lead;

/**
 * Reads a lead's website for an email address and the signals a hook is built
 * from. Sites that block us or render with JavaScript are flagged, not dropped.
 */
class Enricher
{
    /** Where businesses put their address when the home page does not carry it. */
    private const array CONTACT_PATHS = ['/contact', '/contact/', '/contact-us', '/over-ons', '/impressum'];

    /** Addresses that belong to the site's builder, not the business. */
    private const array NOISE = ['example.com', 'sentry', 'wixpress', 'godaddy', 'squarespace', 'wordpress', 'w3.org', 'schema.org'];

    public function __construct(private readonly SiteReader $reader) {}

    /**
     * Fill the lead's email and signals from its site and save it.
     */
    public function enrich(Lead $lead): Lead
    {
        if ($lead->website === null) {
            $lead->signals = $this->signals(null);
            $lead->save();

            return $lead;
        }

        $base = 'https://'.$lead->website;
        $home = $this->reader->fetch($base);

        if ($home === null) {
            $lead->signals = [...$this->signals(null), 'blocked' => true];
            $lead->save();

            return $lead;
        }

        $email = $this->email($home);

        // Contact pages carry the address more often than the home page does.
        foreach (self::CONTACT_PATHS as $path) {
            if ($email !== null) {
                break;
            }

            $page = $this->reader->fetch($base.$path);

            if ($page !== null) {
                $email = $this->email($page);
            }
        }

        $lead->email ??= $email;
        $lead->signals = $this->signals($home);
        $lead->save();

        return $lead;
    }

    /**
     * The first plausible address on the page, mailto links first.
     */
    public function email(string $html): ?string
    {
        preg_match_all('/mailto:([^"\'?\s>]+)/i', $html, $mailto);
        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', html_entity_decode($html), $plain);

        foreach ([...$mailto[1], ...$plain[0]] as $candidate) {
            $candidate = strtolower(trim($candidate));

            if (! filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (preg_match('/\.(png|jpe?g|gif|svg|webp)$/', $candidate)) {
                continue;
            }

            foreach (self::NOISE as $noise) {
                if (str_contains($candidate, $noise)) {
                    continue 2;
                }
            }

            return $candidate;
        }

        return null;
    }

    /**
     * What the home page says about how fresh and how mobile the site is.
     *
     * @return array{copyright_year: int|null, viewport: bool|null, software: list<string>, last_news_at: string|null, blocked: bool, javascript_only: bool}
     */
    public function signals(?string $html): array
    {
        if ($html === null) {
            return ['copyright_year' => null, 'viewport' => null, 'software' => [], 'last_news_at' => null, 'blocked' => false, 'javascript_only' => false];
        }

        return [
            'copyright_year' => $this->copyrightYear($html),
            'viewport' => (bool) preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html),
            'software' => $this->software($html),
            'last_news_at' => $this->lastDate($html),
            'blocked' => false,
            'javascript_only' => $this->javascriptOnly($html),
        ];
    }

    private function copyrightYear(string $html): ?int
    {
        // "© 2017", "copyright 2019", "© 2015 - 2021": the latest year wins.
        preg_match_all('/(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?((?:19|20)\d{2})/iu', $html, $matches);

        return $matches[1] === [] ? null : (int) max($matches[1]);
    }

    /**
     * @return list<string>
     */
    private function software(string $html): array
    {
        $found = [];

        foreach ([
            'WordPress' => '/wp-content|wp-includes|generator"\s+content="WordPress/i',
            'Wix' => '/wix\.com|wixstatic|X-Wix/i',
            'Squarespace' => '/squarespace/i',
            'Joomla' => '/joomla/i',
            'Shopify' => '/cdn\.shopify|myshopify/i',
            'Webflow' => '/webflow/i',
            'Drupal' => '/drupal/i',
            'Jouwweb' => '/jouwweb/i',
        ] as $name => $pattern) {
            if (preg_match($pattern, $html)) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /**
     * The newest date the page mentions in a machine-readable way; a proxy for the last news post.
     */
    private function lastDate(string $html): ?string
    {
        preg_match_all('/datetime=["\']((?:19|20)\d{2}-\d{2}-\d{2})/i', $html, $matches);

        if ($matches[1] === []) {
            return null;
        }

        $dates = array_filter($matches[1], fn (string $date) => $date <= now()->toDateString());

        return $dates === [] ? null : max($dates);
    }

    /**
     * A page that is all scripts and an empty root is rendered by the browser; we only saw the shell.
     */
    private function javascriptOnly(string $html): bool
    {
        $text = trim(strip_tags(preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/is', '', $html) ?? ''));

        return strlen($text) < 200 && preg_match('/<script/i', $html) === 1;
    }
}
