<?php

namespace App\Support\Mail;

/**
 * The part of a reply the lead actually typed, without the quoted mail underneath.
 */
class ReplyText
{
    private const QUOTE_MARKERS = [
        '/^On .+ wrote:\s*$/im',
        '/^Op .+ schreef .*:\s*$/im',
        '/^-{2,}\s*Original Message\s*-{2,}$/im',
        '/^-{2,}\s*Oorspronkelijk bericht\s*-{2,}$/im',
        '/^From: .+$/im',
        '/^Van: .+$/im',
        '/^>/m',
    ];

    public static function strip(string $text): string
    {
        $text = str_replace("\r\n", "\n", trim($text));
        $cut = strlen($text);

        foreach (self::QUOTE_MARKERS as $marker) {
            if (preg_match($marker, $text, $match, PREG_OFFSET_CAPTURE) === 1) {
                $cut = min($cut, $match[0][1]);
            }
        }

        $own = trim(substr($text, 0, $cut));

        return $own === '' ? $text : $own;
    }
}
