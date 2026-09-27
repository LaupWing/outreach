<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sending window
    |--------------------------------------------------------------------------
    |
    | Queued mail goes out between these hours in this timezone, on weekdays
    | only when weekdays_only is set. A message queued outside the window waits
    | for the next opening. Times are hours on a 24-hour clock.
    |
    */

    'window' => [
        'timezone' => env('OUTREACH_TIMEZONE', 'Europe/Amsterdam'),
        'start' => (int) env('OUTREACH_WINDOW_START', 9),
        'end' => (int) env('OUTREACH_WINDOW_END', 17),
        'weekdays_only' => (bool) env('OUTREACH_WEEKDAYS_ONLY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm-up
    |--------------------------------------------------------------------------
    |
    | A mailbox that is warming up sends first_day mails on day one and grows
    | evenly to its daily limit over this many days.
    |
    */

    'warm_up' => [
        'days' => (int) env('OUTREACH_WARM_UP_DAYS', 14),
        'first_day' => (int) env('OUTREACH_WARM_UP_FIRST_DAY', 5),
    ],

];
