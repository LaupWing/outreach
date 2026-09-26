<?php

namespace App\Support;

use App\Models\Mailbox;

/**
 * Checks that a mailbox's SMTP and IMAP credentials work. Bound to a fake in tests.
 */
interface MailboxConnection
{
    /**
     * Try both connections; null when everything works, otherwise the first error in plain words.
     */
    public function check(Mailbox $mailbox): ?string;
}
