<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Support\Mail\InboxCheck;
use Illuminate\Console\Command;

class CheckInboxes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outreach:check-inbox';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read every mailbox for replies and bounces and book them on the leads';

    /**
     * Every box is read, paused ones too: a lead may still answer a mail sent last week.
     * A failing login is recorded on the mailbox and tried again next round.
     */
    public function handle(InboxCheck $check): int
    {
        $totals = ['replies' => 0, 'bounces' => 0, 'skipped' => 0];

        $mailboxes = Mailbox::query()
            ->with('user')
            ->orderBy('id')
            ->cursor();

        foreach ($mailboxes as $mailbox) {
            $result = $check->run($mailbox);

            if ($result['error'] !== null) {
                $this->warn("{$mailbox->address}: {$result['error']}");

                continue;
            }

            foreach ($totals as $key => $total) {
                $totals[$key] += $result[$key];
            }
        }

        $this->info(sprintf('%d replies, %d bounces, %d other mails left alone.', $totals['replies'], $totals['bounces'], $totals['skipped']));

        return self::SUCCESS;
    }
}
