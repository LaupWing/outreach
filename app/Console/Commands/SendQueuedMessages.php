<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Support\Mail\Outbox;
use Illuminate\Console\Command;

class SendQueuedMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outreach:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hand every queued message whose moment has come to its mailbox';

    /**
     * Runs every few minutes from the scheduler. Sends nothing outside the window,
     * so a mail queued for 16:58 that the tick misses goes first thing tomorrow.
     */
    public function handle(Outbox $outbox): int
    {
        if (! $outbox->isOpen()) {
            $this->info('Outside the sending window; nothing sent.');

            return self::SUCCESS;
        }

        $counts = ['sent' => 0, 'failed' => 0, 'queued' => 0];

        foreach ($outbox->due() as $message) {
            $status = $outbox->send($message);

            $counts[$status->value]++;

            if ($status === MessageStatus::Failed) {
                $this->warn("Message {$message->id}: {$message->error}");
            }
        }

        $this->info(sprintf('%d sent, %d failed, %d pushed to a later window.', $counts['sent'], $counts['failed'], $counts['queued']));

        return self::SUCCESS;
    }
}
