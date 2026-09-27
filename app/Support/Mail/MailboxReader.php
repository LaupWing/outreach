<?php

namespace App\Support\Mail;

use App\Models\Mailbox;
use Carbon\CarbonImmutable;

/**
 * Reads what arrived in a mailbox since the last check. Bound to a fake in tests.
 */
interface MailboxReader
{
    /**
     * Mail with a UID above the mailbox's last seen one, oldest first. On the very
     * first check only mail that arrived after the mailbox was added counts.
     *
     * @return list<IncomingMail>
     */
    public function newMail(Mailbox $mailbox): array;

    /**
     * Every inbox mail since the given moment, regardless of what was seen before.
     * For catching up on replies to mail sent before Snelreach was in the loop.
     *
     * @return list<IncomingMail>
     */
    public function mailSince(Mailbox $mailbox, CarbonImmutable $since): array;

    /**
     * What the mailbox sent since the given moment, from its sent folder.
     *
     * @return list<SentMail>
     */
    public function sentMail(Mailbox $mailbox, CarbonImmutable $since): array;
}
