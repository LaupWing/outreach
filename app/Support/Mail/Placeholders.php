<?php

namespace App\Support\Mail;

use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Fills a sequence step's {{placeholders}} from the lead. The plain version;
 * the compose dialog does the same in the browser, and Claude does it better.
 */
class Placeholders
{
    public static function fill(string $text, Lead $lead): string
    {
        $hookSubject = $lead->hook === null
            ? "Jullie website, {$lead->company}"
            : Str::limit(rtrim($lead->hook, '.'), 60, '');

        return str_replace(
            ['{{company}}', '{{hook}}', '{{hook_subject}}'],
            [$lead->company, $lead->hook ?? '', $hookSubject],
            $text,
        );
    }
}
