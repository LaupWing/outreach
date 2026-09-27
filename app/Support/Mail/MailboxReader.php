<?php

namespace App\Support\Mail;

use App\Models\Mailbox;

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
}
