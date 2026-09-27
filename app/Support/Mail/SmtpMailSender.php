<?php

namespace App\Support\Mail;

use App\Models\Message;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Plain-text mail straight over the mailbox's SMTP login, STARTTLS or TLS by port.
 * No HTML, no tracking pixels: it has to look like a person wrote it.
 */
class SmtpMailSender implements MailSender
{
    public function send(Message $message, ?string $inReplyTo = null): void
    {
        $mailbox = $message->mailbox;

        $email = (new Email)
            ->from(new Address($mailbox->address))
            ->to(new Address($message->lead->email, $message->lead->company))
            ->subject($message->subject)
            ->text($message->body);

        $headers = $email->getHeaders();
        $headers->addIdHeader('Message-ID', trim($message->message_id, '<>'));

        if ($inReplyTo !== null) {
            $headers->addIdHeader('In-Reply-To', trim($inReplyTo, '<>'));
            $headers->addIdHeader('References', trim($inReplyTo, '<>'));
        }

        $transport = new EsmtpTransport($mailbox->smtp_host, $mailbox->smtp_port, $mailbox->smtp_port === 465);
        $transport->setUsername($mailbox->username);
        $transport->setPassword($mailbox->password);

        (new Mailer($transport))->send($email);
    }
}
