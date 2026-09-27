<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Local only: what `db:seed` gives the test account so it skips onboarding after migrate:fresh.
    'seed' => [
        'google_places_key' => env('SEED_GOOGLE_PLACES_KEY'),
        'mailbox_address' => env('SEED_MAILBOX_ADDRESS'),
        'mailbox_password' => env('SEED_MAILBOX_PASSWORD'),
    ],

    // Headless Chrome for sites that render with JavaScript. Off unless the server has Chromium.
    'browser' => [
        'enabled' => (bool) env('BROWSER_FETCH', false),
        'chrome_path' => env('BROWSER_CHROME_PATH'),
        'node_binary' => env('BROWSER_NODE_BINARY'),
    ],

    'outreach' => [
        // The account the local (stdio) MCP server acts as; the first account when unset.
        'mcp_user' => env('OUTREACH_MCP_USER'),
    ],

    'google' => [
        'places' => [
            'key' => env('GOOGLE_PLACES_KEY'),
            // Text Search Enterprise: 1,000 free requests a month, then $35 per 1,000.
            'free_requests' => (int) env('GOOGLE_PLACES_FREE_REQUESTS', 1000),
            'price_per_1000' => (int) env('GOOGLE_PLACES_PRICE_PER_1000', 35),
        ],
    ],

];
