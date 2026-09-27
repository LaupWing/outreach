<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Mail\FollowUps;
use Illuminate\Console\Command;

class QueueFollowUps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outreach:follow-up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue the next sequence step for every lead whose follow-up is due';

    public function handle(FollowUps $followUps): int
    {
        $totals = ['queued' => 0, 'finished' => 0, 'waiting' => 0];

        foreach (User::query()->cursor() as $user) {
            foreach ($followUps->run($user) as $key => $count) {
                $totals[$key] += $count;
            }
        }

        $this->info(sprintf('%d follow-ups queued, %d sequences finished, %d waiting for mailbox room.', $totals['queued'], $totals['finished'], $totals['waiting']));

        return self::SUCCESS;
    }
}
