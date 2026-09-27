<?php

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

/**
 * One mail found in a mailbox's inbox, reduced to what the check needs.
 */
final class IncomingMail
{
    /**
     * @param  list<string>  $references  Message-IDs this mail refers to, In-Reply-To first.
     */
    public function __construct(
        public int $uid,
        public string $from,
        public string $subject,
        public string $text,
        public ?string $messageId,
        public array $references,
        public CarbonImmutable $receivedAt,
        public bool $autoSubmitted = false,
    ) {}

    /**
     * Out-of-office and other machine replies, or a delivery report from the mail system.
     */
    public function isBounce(): bool
    {
        return preg_match('/^(mailer-daemon|postmaster|no-?reply\+bounce)/i', $this->from) === 1
            || preg_match('/delivery status|undeliverable|delivery failed|mail delivery|not delivered|could not be delivered|failure notice/i', $this->subject) === 1;
    }
}
