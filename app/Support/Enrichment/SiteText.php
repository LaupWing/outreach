<?php

namespace App\Support\Enrichment;

use Illuminate\Support\Str;

/**
 * The readable text of a page: what the business says, without menus, cookie bars,
 * headers and footers, whitespace collapsed and capped so ten leads fit in one answer.
 */
class SiteText
{
    public static function from(string $html, int $limit = 1000): string
    {
        $html = preg_replace('/<(script|style|noscript|svg|nav|header|footer|form|iframe)[^>]*>.*?<\/\1>/is', ' ', $html) ?? '';
        // Cookie and consent banners by their usual ids and classes.
        $html = preg_replace('/<(div|section|aside)[^>]*(cookie|consent|gdpr|cmplz|banner)[^>]*>.*?<\/\1>/is', ' ', $html) ?? '';
        $html = preg_replace('/<br\s*\/?>|<\/(p|div|li|h[1-6]|tr|section|article)>/i', "\n", $html) ?? '';

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? '';

        // Short lines are menu items and buttons; keep the sentences.
        $lines = array_filter(
            array_map('trim', explode("\n", $text)),
            fn (string $line) => mb_strlen($line) >= 15,
        );

        return Str::limit(trim(implode("\n", array_unique($lines))), $limit);
    }
}
