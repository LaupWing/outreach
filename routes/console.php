<?php

use Illuminate\Support\Facades\Schedule;

// Queued mail leaves in small batches through the day; a tick that finds nothing due is free.
Schedule::command('outreach:send')->everyFiveMinutes()->withoutOverlapping();
