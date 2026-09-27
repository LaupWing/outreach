<?php

use App\Mcp\Servers\OutreachServer;
use Laravel\Mcp\Facades\Mcp;

// Claude Code / Claude Desktop start this over stdio: `php artisan mcp:start outreach`.
Mcp::local('outreach', OutreachServer::class);

// Hosted: Claude connects to /mcp over HTTP, logs in through the app and approves
// once on the consent page. OAuth discovery and client registration come with it.
Mcp::oauthRoutes();

Mcp::web('/mcp', OutreachServer::class)->middleware('auth:api');
