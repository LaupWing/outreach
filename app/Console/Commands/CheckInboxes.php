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
     * Paused boxes are read too: a lead may still answer a mail sent last week.
     */
    public function handle(InboxCheck $check): int
    {
        $totals = ['replies' => 0, 'bounces' => 0, 'skipped' => 0];

        foreach (Mailbox::query()->with('user')->orderBy('id')->cursor() as $mailbox) {
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
