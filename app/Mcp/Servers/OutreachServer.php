<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\CatalogApp;
use App\Mcp\Resources\LeadCardApp;
use App\Mcp\Resources\LeadListApp;
use App\Mcp\Resources\MailCardApp;
use App\Mcp\Resources\StatsApp;
use App\Mcp\Tools\CheckInbox;
use App\Mcp\Tools\CreateLeads;
use App\Mcp\Tools\CreateOffer;
use App\Mcp\Tools\EnrichLead;
use App\Mcp\Tools\EnrichRun;
use App\Mcp\Tools\ImportSentMail;
use App\Mcp\Tools\LeadContext;
use App\Mcp\Tools\ListLeads;
use App\Mcp\Tools\ListMailboxes;
use App\Mcp\Tools\ListNiches;
use App\Mcp\Tools\ListOffers;
use App\Mcp\Tools\PreviewMail;
use App\Mcp\Tools\ScrapeStatus;
use App\Mcp\Tools\SearchLeads;
use App\Mcp\Tools\SendDraft;
use App\Mcp\Tools\SendMail;
use App\Mcp\Tools\SendStep;
use App\Mcp\Tools\Stats;
use App\Mcp\Tools\UpdateLeads;
use App\Mcp\Tools\UpdateNiche;
use App\Mcp\Tools\UpdateOffer;
use App\Mcp\Tools\WhoNeedsFollowUp;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Icon;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Snelreach')]
#[Icon('favicon.svg', mimeType: 'image/svg+xml')]
#[Icon('apple-touch-icon.png', mimeType: 'image/png', sizes: ['180x180'])]
#[Version('0.2.0')]
#[Instructions(<<<'TEXT'
Snelreach: cold outreach for Dutch SMBs. The app finds businesses, reads their sites, sends mail from the account's mailboxes (spread over the sending hours, with daily limits), reads the inboxes for replies and bounces, and plans follow-ups. You do the thinking and the writing.

Niches are the kinds of business you try (list_niches, update_niche: status idea/testing/proven/dropped, why, findings). Finding leads: search_leads (query, place, niche) → enrich_lead for a few, or enrich_run for all → list_leads with with_email=true. Leads you already know go in with create_leads, many at once. A search costs one of 1,000 free Google requests a month per page of 20.

Writing: an offer has a mail sequence (list_offers, create_offer, update_offer). Steps contain {{tags}}. Built-in tags come from the lead itself: company, city, email, phone, website, hook (one sentence about their site, set with update_leads) and hook_subject (the hook as a subject line). Every other tag, like {{compliment}} or {{first_name}}, is yours to fill per lead; the offer's tag_explanations say what it should contain. Get lead_context (with_site_text=true when you need to read the site), then preview_mail to show the mail as a card (the user can press Send or edit it in the app), or send_step / send to queue it straight away. Values you pass are saved as facts on the lead, so later steps reuse them. lead_context lists every step with its missing_tags: fill the tags of ALL steps in one go (pass them all in values, or with update_leads), so the automatic follow-ups can go out without you. Mail lands in the outbox and leaves at the next free moment within the account's sending hours; nothing goes out immediately.

Following up: with auto follow-up on, the app sends the next step by itself once it is due, unless a tag is unfilled. who_needs_follow_up shows what waits and why; check_inbox shows who replied. Answer a reply with send and a "Re:" subject. Mail sent by hand before Snelreach: import_sent_mail pulls it in from the sent folder, with its replies. stats shows how niches, offers and mailboxes perform.
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
        CreateLeads::class,
        ListMailboxes::class,
        ListNiches::class,
        UpdateNiche::class,
        ListOffers::class,
        CreateOffer::class,
        UpdateOffer::class,
        LeadContext::class,
        UpdateLeads::class,
        PreviewMail::class,
        SendStep::class,
        SendMail::class,
        SendDraft::class,
        CheckInbox::class,
        ImportSentMail::class,
        WhoNeedsFollowUp::class,
        Stats::class,
    ];

    protected array $resources = [
        MailCardApp::class,
        LeadCardApp::class,
        LeadListApp::class,
        StatsApp::class,
        CatalogApp::class,
    ];

    protected array $prompts = [];
}
