/**
 * Mirrors the tables from the briefing, so the mock data can be swapped for
 * real props without touching the components.
 */

export type NicheStatus = 'testing' | 'idea' | 'proven' | 'dropped';

export type Niche = {
    id: number;
    name: string;
    status: NicheStatus;
    why: string | null;
    findings: string | null;
};

export type OfferStatus = 'active' | 'idea' | 'stopped';

export type Offer = {
    id: number;
    name: string;
    niche_id: number;
    description: string | null;
    status: OfferStatus;
};

export type LeadStatus =
    | 'new'
    | 'emailed'
    | 'followed_up'
    | 'replied'
    | 'customer'
    | 'no'
    | 'undeliverable';

export type LeadSource = 'places' | 'register' | 'manual';

/** What the enricher found on the site, the raw material for the hook. */
export type LeadSignals = {
    copyright_year: number | null;
    viewport: boolean | null;
    software: string[];
    last_news_at: string | null;
    /** Sites that block us or render with JavaScript are flagged, not thrown away. */
    blocked: boolean;
    javascript_only: boolean;
};

export type Lead = {
    id: number;
    company: string;
    email: string | null;
    phone: string | null;
    website: string | null;
    city: string | null;
    niche_id: number;
    offer_id: number | null;
    status: LeadStatus;
    source: LeadSource;
    /** The run that found it, when the source is places. */
    scrape_run_id: number | null;
    hook: string | null;
    signals: LeadSignals | null;
    last_contact_at: string | null;
    next_action_at: string | null;
    created_at: string;
};

export type MailboxType = 'gmail' | 'imap';

export type MailboxStatus = 'active' | 'warming_up' | 'paused';

export type Mailbox = {
    id: number;
    address: string;
    type: MailboxType;
    daily_limit: number;
    sent_today: number;
    status: MailboxStatus;
};

export type MessageStatus = 'draft' | 'sent' | 'bounced' | 'replied';

export type Message = {
    id: number;
    lead_id: number;
    mailbox_id: number;
    step: number;
    subject: string;
    body: string;
    sent_at: string | null;
    status: MessageStatus;
    thread_id: string | null;
    /** The reply text, once one came in. Lives on the message for now; the inbox check fills it. */
    reply: { body: string; received_at: string } | null;
};

export type ScrapeRunStatus = 'queued' | 'running' | 'done' | 'failed';

/** One Google Places search: what was asked, what it cost and what came out. */
export type ScrapeRun = {
    id: number;
    query: string;
    place: string;
    niche_id: number;
    status: ScrapeRunStatus;
    /** Places API requests spent; every page of 20 results is one. */
    requests: number;
    found: number;
    with_email: number;
    blocked: number;
    started_at: string;
    finished_at: string | null;
};

/** Free-tier usage of the Places SKU we call, per calendar month. */
export type PlacesUsage = {
    sku: string;
    used: number;
    free_limit: number;
    /** USD per 1,000 requests once the free volume is used up. */
    price_per_1000: number;
    resets_at: string;
};

/** One mail in an offer's sequence; the body carries placeholders for the hook. */
export type SequenceStep = {
    id: number;
    offer_id: number;
    step: number;
    days_after_previous: number;
    subject: string;
    body: string;
};
