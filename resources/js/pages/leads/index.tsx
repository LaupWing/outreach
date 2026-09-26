import { Head, Link, usePage } from '@inertiajs/react';
import {
    Database,
    MapPin,
    Plus,
    Radar,
    SlidersHorizontal,
    Tag,
    Target,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterCombobox } from '@/components/filters/filter-combobox';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { leadStatuses } from '@/components/lead-status-badge';
import { LeadDialog } from '@/components/lead-dialog';
import { LeadPanel } from '@/components/lead-panel';
import { LeadsTable } from '@/components/leads-table';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { mockLeads } from '@/mock/leads';
import { mockMailboxes } from '@/mock/mailboxes';
import { mockMessages } from '@/mock/messages';
import { mockNiches } from '@/mock/niches';
import { mockOffers } from '@/mock/offers';
import { mockScrapeRuns } from '@/mock/scrape-runs';
import { mockSequenceSteps } from '@/mock/sequence-steps';
import { index as leadsIndex } from '@/routes/leads';
import type { Lead, LeadSource, LeadStatus } from '@/types';

const statusOptions: FilterOption[] = (
    Object.keys(leadStatuses) as LeadStatus[]
).map((value) => ({ value, label: leadStatuses[value].label }));

const sourceOptions: { value: LeadSource; label: string }[] = [
    { value: 'places', label: 'Google Places' },
    { value: 'register', label: 'Register' },
    { value: 'manual', label: 'Manual' },
];

const nicheOptions: FilterOption[] = mockNiches.map((niche) => ({
    value: String(niche.id),
    label: niche.name,
}));

const offerOptions: FilterOption[] = mockOffers.map((offer) => ({
    value: String(offer.id),
    label: offer.name,
}));

const cityOptions: FilterOption[] = [
    ...new Set(mockLeads.flatMap((lead) => (lead.city ? [lead.city] : []))),
]
    .sort()
    .map((city) => ({ value: city, label: city }));

const iconClassName = 'size-3.5 text-muted-foreground';

// The mailbox that last mailed each lead; the messages carry it, the lead does not.
const sentFrom = Object.fromEntries(
    mockLeads.map((lead) => {
        const last = mockMessages.filter((m) => m.lead_id === lead.id).at(-1);

        return [
            lead.id,
            mockMailboxes.find((mailbox) => mailbox.id === last?.mailbox_id)
                ?.address,
        ];
    }),
);

export default function LeadsIndex() {
    // Search and other pages deep-link to a lead with ?lead=ID.
    const { url } = usePage();
    const params = new URLSearchParams(url.split('?')[1] ?? '');
    const linked = params.get('lead');
    // A scrape run's "Open in leads" narrows the list to what that run found.
    const runId = params.get('run');
    const run = mockScrapeRuns.find((item) => String(item.id) === runId) ?? null;
    const linkedLead =
        mockLeads.find((lead) => String(lead.id) === linked) ?? null;

    const [statuses, setStatuses] = useState<string[]>([]);
    const [sources, setSources] = useState<string[]>([]);
    const [niches, setNiches] = useState<string[]>([]);
    const [offers, setOffers] = useState<string[]>([]);
    const [cities, setCities] = useState<string[]>([]);
    const [selected, setSelected] = useState<Lead | null>(linkedLead);
    const compact = selected !== null;
    const hiddenActive =
        niches.length + offers.length + cities.length + sources.length;
    // Keeps the last lead while the panel slides shut.
    const [panelLead, setPanelLead] = useState<Lead | null>(linkedLead);

    // Same page, new ?lead: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linkedLead) {
            setSelected(linkedLead);
            setPanelLead(linkedLead);
        }
    }, [linked]); // eslint-disable-line react-hooks/exhaustive-deps

    // Client-side for now; these become query parameters once the leads come from Laravel.
    const leads = mockLeads.filter(
        (lead) =>
            (statuses.length === 0 || statuses.includes(lead.status)) &&
            (sources.length === 0 || sources.includes(lead.source)) &&
            (niches.length === 0 || niches.includes(String(lead.niche_id))) &&
            (offers.length === 0 || offers.includes(String(lead.offer_id))) &&
            (cities.length === 0 || (lead.city && cities.includes(lead.city))) &&
            (run === null || lead.scrape_run_id === run.id),
    );

    const moreFilters = (
        <>
            <FilterCombobox
                label="Niche"
                icon={<Target className={iconClassName} />}
                options={nicheOptions}
                selected={niches}
                onChange={setNiches}
                searchPlaceholder="Search niches…"
            />
            <FilterCombobox
                label="Offer"
                icon={<Tag className={iconClassName} />}
                options={offerOptions}
                selected={offers}
                onChange={setOffers}
                searchPlaceholder="Search offers…"
            />
            <FilterCombobox
                label="City"
                icon={<MapPin className={iconClassName} />}
                options={cityOptions}
                selected={cities}
                onChange={setCities}
                searchPlaceholder="Search cities…"
            />
            <FilterMenu
                label="Source"
                icon={<Database className={iconClassName} />}
                options={sourceOptions}
                selected={sources}
                onChange={setSources}
            />
        </>
    );

    return (
        <>
            <Head title="Leads" />

            {/* The panel spans the filter bar and the table, like the card in the reference. */}
            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4">
                        <span className="mr-1 flex shrink-0 items-center gap-2 text-sm whitespace-nowrap text-muted-foreground">
                            <SlidersHorizontal className="size-4" />
                            Filters:
                        </span>
                        {run && (
                            <Link
                                href={leadsIndex()}
                                className="flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-(--raised-border) bg-accent px-2.5 text-xs shadow-(--raised-shadow) hover:text-foreground"
                                aria-label="Show all leads"
                            >
                                <Radar className="size-3.5 text-muted-foreground" />
                                <span className="text-muted-foreground">Scrape</span>
                                {run.query} in {run.place}
                                <X className="size-3.5 text-muted-foreground" />
                            </Link>
                        )}
                        <FilterMenu
                            label="Status"
                            icon={<Tag className={iconClassName} />}
                            options={statusOptions}
                            selected={statuses}
                            onChange={setStatuses}
                        />
                        {/* With the panel open there is no room for four filters: the rest live in a popover. */}
                        {compact ? (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <button
                                        type="button"
                                        className="flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-dashed border-border px-2.5 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                                    >
                                        <Plus className="size-3.5" />
                                        More
                                        {hiddenActive > 0 && (
                                            <span className="rounded-full bg-accent px-1.5 text-[10px] text-foreground tabular-nums">
                                                {hiddenActive}
                                            </span>
                                        )}
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent align="start" className="flex w-auto flex-col gap-2 p-2">
                                    {moreFilters}
                                </PopoverContent>
                            </Popover>
                        ) : (
                            moreFilters
                        )}
                    </div>
                    <LeadsTable
                        leads={leads}
                        niches={mockNiches}
                        offers={mockOffers}
                        sentFrom={sentFrom}
                        selectedId={selected?.id ?? null}
                        onSelect={(lead) => {
                            setSelected(lead);
                            setPanelLead(lead);
                        }}
                    />
                </div>
                <LeadPanel
                    lead={panelLead}
                    niche={mockNiches.find((niche) => niche.id === panelLead?.niche_id)}
                    offer={mockOffers.find((offer) => offer.id === panelLead?.offer_id)}
                    niches={mockNiches}
                    offers={mockOffers}
                    messages={mockMessages.filter((message) => message.lead_id === panelLead?.id)}
                    mailboxes={mockMailboxes}
                    steps={mockSequenceSteps}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: leadsIndex(), icon: Users }],
    actions: <LeadDialog niches={mockNiches} offers={mockOffers} />,
};
