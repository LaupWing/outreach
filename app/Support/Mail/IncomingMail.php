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
        return $this->isDeliveryReport() && ! $this->isTemporaryFailure();
    }

    /**
     * Anything the mail system sends about a delivery: a bounce, or just a delay.
     */
    public function isDeliveryReport(): bool
    {
        return preg_match('/^(mailer-daemon|postmaster|no-?reply\+bounce)/i', $this->from) === 1
            || preg_match('/delivery status|undeliverable|delivery failed|mail delivery|not delivered|could not be delivered|failure notice|delivery incomplete|delayed/i', $this->subject) === 1;
    }

    /**
     * "Delivery incomplete, Gmail will retry": the mail may still arrive, so it is not a bounce.
     */
    public function isTemporaryFailure(): bool
    {
        $text = $this->subject."\n".$this->text;

        if (preg_match('/failed permanently|permanent(ly)? (error|failure)|\b5\.\d\.\d+\b|\b55\d\b/i', $text) === 1) {
            return false;
        }

        return preg_match('/delivery incomplete|will retry|temporar(y|ily)|delayed|\b4\.\d\.\d+\b|\b4[0-9]{2}\b/i', $text) === 1;
    }
}
