<?php

use Illuminate\Support\Facades\Schedule;

// Queued mail leaves in small batches through the day; a tick that finds nothing due is free.
Schedule::command('outreach:send')->everyFiveMinutes()->withoutOverlapping();

// Replies and bounces come back on the lead within minutes, not the next morning.
Schedule::command('outreach:check-inbox')->everyTenMinutes()->withoutOverlapping();
