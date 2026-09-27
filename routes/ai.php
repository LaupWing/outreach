<?php

use App\Mcp\Servers\OutreachServer;
use Laravel\Mcp\Facades\Mcp;

// Claude Code / Claude Desktop start this over stdio: `php artisan mcp:start outreach`.
Mcp::local('outreach', OutreachServer::class);
