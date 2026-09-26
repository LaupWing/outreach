<?php

namespace App\Support;

use App\Models\Mailbox;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Logs in over SMTP (STARTTLS or TLS by port) and over IMAP (TLS) and logs
 * straight out again. No mail is sent, nothing is read.
 */
class SmtpImapConnection implements MailboxConnection
{
    public function check(Mailbox $mailbox): ?string
    {
        return $this->checkSmtp($mailbox) ?? $this->checkImap($mailbox);
    }

    private function checkSmtp(Mailbox $mailbox): ?string
    {
        try {
            $transport = new EsmtpTransport($mailbox->smtp_host, $mailbox->smtp_port, $mailbox->smtp_port === 465);
            $transport->setUsername($mailbox->username);
            $transport->setPassword($mailbox->password);
            $transport->start();
            $transport->stop();
        } catch (Throwable $exception) {
            return 'SMTP: '.$this->plain($exception->getMessage());
        }

        return null;
    }

    /**
     * IMAP without the php-imap extension: a TLS socket, one LOGIN, one LOGOUT.
     */
    private function checkImap(Mailbox $mailbox): ?string
    {
        $socket = @stream_socket_client(
            "tls://{$mailbox->imap_host}:{$mailbox->imap_port}",
            $errorCode,
            $errorMessage,
            10,
        );

        if ($socket === false) {
            return 'IMAP: '.($errorMessage ?: 'could not connect');
        }

        try {
            stream_set_timeout($socket, 10);
            fgets($socket);

            fwrite($socket, sprintf("a1 LOGIN %s %s\r\n", $this->quote($mailbox->username), $this->quote($mailbox->password)));
            $reply = $this->readUntilTagged($socket, 'a1');

            if (! str_starts_with($reply, 'a1 OK')) {
                return 'IMAP: '.$this->plain(trim(substr($reply, 3)) ?: 'login refused');
            }

            fwrite($socket, "a2 LOGOUT\r\n");
        } finally {
            fclose($socket);
        }

        return null;
    }

    /**
     * @param  resource  $socket
     */
    private function readUntilTagged($socket, string $tag): string
    {
        while (($line = fgets($socket)) !== false) {
            if (str_starts_with($line, $tag.' ')) {
                return $line;
            }
        }

        return $tag.' NO connection closed';
    }

    private function quote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }

    /**
     * Server error strings are long and technical; keep the first line.
     */
    private function plain(string $message): string
    {
        return trim(strtok($message, "\n") ?: $message);
    }
}
