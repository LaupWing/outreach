<?php

namespace App\Support\Mail;

use App\Models\Message;

/**
 * Hands one message to the mail server of its mailbox. Bound to a fake in tests.
 */
interface MailSender
{
    /**
     * Send the message over the mailbox's SMTP connection. Throws when the server refuses.
     * The message carries its lead, mailbox and headers (message_id, thread) already.
     *
     * @param  string|null  $inReplyTo  The Message-ID of the mail this one continues, when it does.
     */
    public function send(Message $message, ?string $inReplyTo = null): void;
}
