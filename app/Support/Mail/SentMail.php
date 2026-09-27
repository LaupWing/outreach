<?php

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

/**
 * One mail found in a mailbox's sent folder: what went out before Snelreach kept track.
 */
final class SentMail
{
    /**
     * @param  list<string>  $to  Recipient addresses, lowercased.
     */
    public function __construct(
        public int $uid,
        public array $to,
        public string $subject,
        public string $text,
        public ?string $messageId,
        public CarbonImmutable $sentAt,
    ) {}
}
