<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\EnrichLead;
use App\Mcp\Tools\EnrichRun;
use App\Mcp\Tools\ListLeads;
use App\Mcp\Tools\ScrapeStatus;
use App\Mcp\Tools\SearchLeads;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Snelreach')]
#[Version('0.1.0')]
#[Instructions(<<<'TEXT'
Cold outreach for Dutch SMBs: find businesses in a niche, read their websites for an email address and a hook, then mail them from the account's mailboxes.

Typical flow: search_leads (query, place, niche) → enrich_lead for a handful, or enrich_run for all of them → list_leads with with_email=true to see who can be mailed.
Searches cost one free Google request per page of 20; the account has 1,000 a month. Enrichment reads the sites and takes a few seconds per lead.
TEXT)]
class OutreachServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        SearchLeads::class,
        EnrichLead::class,
        EnrichRun::class,
        ScrapeStatus::class,
        ListLeads::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
