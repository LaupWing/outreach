<?php

namespace App\Support\Mail;

use App\Models\Mailbox;
use Carbon\CarbonImmutable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * IMAP over TLS with the mailbox's app password. Mail is peeked, never marked read.
 */
class ImapMailboxReader implements MailboxReader
{
    public function newMail(Mailbox $mailbox): array
    {
        $client = (new ClientManager)->make([
            'host' => $mailbox->imap_host,
            'port' => $mailbox->imap_port,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $mailbox->username,
            'password' => $mailbox->password,
            'protocol' => 'imap',
            'timeout' => 20,
        ]);

        $client->connect();

        try {
            $query = $client->getFolder('INBOX')->query()->leaveUnread();

            $messages = $mailbox->last_seen_uid === null
                ? $query->whereSince($mailbox->created_at->startOfDay())->get()
                : $query->getByUidGreater($mailbox->last_seen_uid);

            $mail = [];

            foreach ($messages as $message) {
                $incoming = $this->toIncoming($message);

                if ($mailbox->last_seen_uid === null && $incoming->receivedAt->lessThan($mailbox->created_at)) {
                    continue;
                }

                $mail[] = $incoming;
            }

            usort($mail, fn (IncomingMail $a, IncomingMail $b) => $a->uid <=> $b->uid);

            return $mail;
        } finally {
            $client->disconnect();
        }
    }

    private function toIncoming(Message $message): IncomingMail
    {
        $from = $message->getFrom()->first();
        $references = [];

        foreach ([$message->getInReplyTo()->toString(), $message->getReferences()->toString()] as $header) {
            preg_match_all('/<[^>]+>/', $header, $matches);
            $references = [...$references, ...$matches[0]];
        }

        $text = $message->getTextBody();

        if ($text === '' && $message->hasHTMLBody()) {
            $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $message->getHTMLBody()))));
        }

        $date = $message->getDate()->first();

        return new IncomingMail(
            uid: (int) $message->getUid(),
            from: strtolower((string) ($from?->mail ?? '')),
            subject: $message->getSubject()->toString(),
            text: $text,
            messageId: $this->firstId($message->getMessageId()->toString()),
            references: array_values(array_unique($references)),
            receivedAt: $date instanceof \DateTimeInterface ? CarbonImmutable::instance($date) : CarbonImmutable::now(),
            autoSubmitted: ! in_array(strtolower($message->getHeader()?->get('auto-submitted')->toString() ?? ''), ['', 'no'], true),
        );
    }

    private function firstId(string $header): ?string
    {
        return preg_match('/<[^>]+>/', $header, $match) === 1 ? $match[0] : null;
    }
}
