<?php

return [

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
