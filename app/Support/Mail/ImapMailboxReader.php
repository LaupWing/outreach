<?php

namespace App\Support\Mail;

use App\Models\Mailbox;
use Carbon\CarbonImmutable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * IMAP over TLS with the mailbox's app password. Mail is peeked, never marked read.
 */
class ImapMailboxReader implements MailboxReader
{
    /**
     * Folder names providers use for sent mail when the server does not flag it, most common first.
     */
    private const SENT_FOLDERS = ['[Gmail]/Sent Mail', '[Gmail]/Verzonden berichten', 'Sent', 'Sent Items', 'Sent Messages', 'INBOX.Sent', 'Verzonden', 'Verzonden items'];

    public function newMail(Mailbox $mailbox): array
    {
        $client = $this->connect($mailbox);

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

    public function mailSince(Mailbox $mailbox, CarbonImmutable $since): array
    {
        $client = $this->connect($mailbox);

        try {
            $messages = $client->getFolder('INBOX')->query()->leaveUnread()->whereSince($since->startOfDay())->get();

            $mail = [];

            foreach ($messages as $message) {
                $incoming = $this->toIncoming($message);

                if ($incoming->receivedAt->greaterThanOrEqualTo($since)) {
                    $mail[] = $incoming;
                }
            }

            usort($mail, fn (IncomingMail $a, IncomingMail $b) => $a->uid <=> $b->uid);

            return $mail;
        } finally {
            $client->disconnect();
        }
    }

    public function sentMail(Mailbox $mailbox, CarbonImmutable $since): array
    {
        $client = $this->connect($mailbox);

        try {
            $folder = $this->sentFolder($client);

            $messages = $folder->query()->leaveUnread()->whereSince($since->startOfDay())->get();

            $mail = [];

            foreach ($messages as $message) {
                $date = $message->getDate()->first();
                $sentAt = $date instanceof \DateTimeInterface ? CarbonImmutable::instance($date) : CarbonImmutable::now();

                if ($sentAt->lessThan($since)) {
                    continue;
                }

                $to = [];

                foreach ($message->getTo()->all() as $address) {
                    $to[] = strtolower((string) ($address->mail ?? ''));
                }

                $text = $message->getTextBody();

                if ($text === '' && $message->hasHTMLBody()) {
                    $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $message->getHTMLBody()))));
                }

                $mail[] = new SentMail(
                    uid: (int) $message->getUid(),
                    to: array_values(array_filter($to)),
                    subject: $message->getSubject()->toString(),
                    text: $text,
                    messageId: $this->firstId($message->getMessageId()->toString()),
                    sentAt: $sentAt,
                );
            }

            usort($mail, fn (SentMail $a, SentMail $b) => $a->sentAt <=> $b->sentAt);

            return $mail;
        } finally {
            $client->disconnect();
        }
    }

    /**
     * The sent folder, whatever language the account is in: the server marks it with
     * the \Sent attribute (RFC 6154); only when it does not, fall back to known names.
     */
    private function sentFolder(Client $client): Folder
    {
        $listing = $client->getConnection()->folders('', '*')->data();

        foreach ($listing as $name => $item) {
            $flags = array_map('strtolower', $item['flags'] ?? []);

            if (in_array('\sent', $flags, true)) {
                $folder = $client->getFolder($name, $item['delimiter'] ?? null);

                if ($folder !== null) {
                    return $folder;
                }
            }
        }

        foreach (self::SENT_FOLDERS as $name) {
            $folder = $client->getFolderByName($name, true);

            if ($folder !== null) {
                return $folder;
            }
        }

        throw new \RuntimeException('No sent folder found; the server did not flag one and none of the usual names exist: '.implode(', ', array_keys($listing)).'.');
    }

    private function connect(Mailbox $mailbox): Client
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

        return $client;
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
