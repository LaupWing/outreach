<?php

namespace App\Jobs;

use App\Models\Mailbox;
use App\Support\Mail\SentMailImport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Reads a mailbox's sent folder and inbox since a date, off the request so an MCP
 * call is not left hanging. The outcome lands in the cache for the tool to report.
 */
class ImportSentMailJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public Mailbox $mailbox,
        public CarbonImmutable $since,
        public bool $createLeads = false,
        public ?int $nicheId = null,
    ) {}

    public static function statusKey(int $userId): string
    {
        return "sent-mail-import:{$userId}";
    }

    public function handle(SentMailImport $import): void
    {
        $key = self::statusKey($this->mailbox->user_id);
        $status = Cache::get($key, ['state' => 'running', 'mailboxes' => []]);

        $result = $import->run($this->mailbox, $this->since, $this->createLeads, $this->nicheId);

        $status['mailboxes'][$this->mailbox->address] = $result;
        $status['state'] = count(array_filter($status['mailboxes'])) >= ($status['expected'] ?? 1) ? 'done' : 'running';
        $status['finished_at'] = now()->toJSON();

        Cache::put($key, $status, now()->addDay());
    }
}
