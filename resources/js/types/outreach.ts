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
    hook: string | null;
    signals: LeadSignals | null;
    last_contact_at: string | null;
    next_action_at: string | null;
    created_at: string;
};
