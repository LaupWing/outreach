import { Head, Link, router, usePage } from '@inertiajs/react';
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
import type { LeadNote } from '@/components/lead-activity';
import { LeadPanel } from '@/components/lead-panel';
import { LeadsTable, type LeadRow } from '@/components/leads-table';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { index as leadsIndex } from '@/routes/leads';
import type {
    LeadSource,
    LeadStatus,
    Mailbox,
    Message,
    Niche,
    Offer,
    ScrapeRun,
    SequenceStep,
} from '@/types';

type PageProps = {
    /** Fifty at a time; Inertia appends the next page as you scroll. */
    leads: { data: LeadRow[] };
    /** How many match the current filters in total. */
    total: number;
    /** The lead from ?lead=ID, whether or not it sits in the loaded rows. */
    linked: LeadRow | null;
    /** Mails and notes of that lead; null when none is open. */
    thread: { messages: Message[]; notes: LeadNote[] } | null;
    niches: Niche[];
    offers: Offer[];
    cities: string[];
    mailboxes: Mailbox[];
    steps: SequenceStep[];
    /** The scrape run the list is narrowed to, from ?run=ID. */
    run: Pick<ScrapeRun, 'id' | 'query' | 'place'> | null;
};

/** The filters as they live in the URL; the server does the filtering. */
type Filters = {
    status: string[];
    source: string[];
    niche: string[];
    offer: string[];
    city: string[];
};

const filterKeys: (keyof Filters)[] = ['status', 'source', 'niche', 'offer', 'city'];

const statusOptions: FilterOption[] = (
    Object.keys(leadStatuses) as LeadStatus[]
).map((value) => ({ value, label: leadStatuses[value].label }));

const sourceOptions: { value: LeadSource; label: string }[] = [
    { value: 'places', label: 'Google Places' },
    { value: 'register', label: 'Register' },
    { value: 'manual', label: 'Manual' },
];

const iconClassName = 'size-3.5 text-muted-foreground';

/** Reads `status[]=a&status[]=b` style parameters back into arrays. */
function filtersFromUrl(url: string): Filters {
    const params = new URLSearchParams(url.split('?')[1] ?? '');
    const list = (key: string) => [...params.getAll(`${key}[]`), ...params.getAll(key)];

    return Object.fromEntries(filterKeys.map((key) => [key, list(key)])) as Filters;
}

export default function LeadsIndex() {
    const { url, props } = usePage<PageProps>();
    const { leads, total, linked, thread, niches, offers, cities, mailboxes, steps, run } = props;

    const params = new URLSearchParams(url.split('?')[1] ?? '');
    const linkedId = params.get('lead');
    const filters = filtersFromUrl(url);

    const nicheOptions: FilterOption[] = niches.map((niche) => ({ value: String(niche.id), label: niche.name }));
    const offerOptions: FilterOption[] = offers.map((offer) => ({ value: String(offer.id), label: offer.name }));
    const cityOptions: FilterOption[] = cities.map((city) => ({ value: city, label: city }));

    const [selectedId, setSelectedId] = useState<number | null>(linked?.id ?? null);
    // Keeps the last lead while the panel slides shut.
    const [panelLead, setPanelLead] = useState<LeadRow | null>(linked);
    const compact = selectedId !== null;
    const hiddenActive = filters.niche.length + filters.offer.length + filters.city.length + filters.source.length;

    // Same page, new ?lead: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelectedId(linked.id);
            setPanelLead(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps

    // After a save the props come back fresh; the panel shows the new copy of its lead.
    useEffect(() => {
        setPanelLead((current) =>
            current ? (leads.data.find((lead) => lead.id === current.id) ?? linked ?? current) : current,
        );
    }, [leads, linked]);

    // Every filter change asks the server for page one again, keeping the open lead and run.
    const visit = (next: Partial<Filters>, extra: Record<string, string | number | null> = {}) => {
        router.get(
            leadsIndex().url,
            {
                ...filters,
                ...next,
                run: run?.id ?? null,
                lead: selectedId,
                ...extra,
            },
            { only: ['leads', 'total', 'linked', 'thread'], reset: ['leads'], preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Opening a row fetches only its thread; the table stays where it is.
    const select = (lead: LeadRow) => {
        setSelectedId(lead.id);
        setPanelLead(lead);
        router.get(
            leadsIndex().url,
            { ...filters, run: run?.id ?? null, lead: lead.id },
            { only: ['thread', 'linked'], preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const moreFilters = (
        <>
            <FilterCombobox label="Niche" icon={<Target className={iconClassName} />} options={nicheOptions} selected={filters.niche} onChange={(niche) => visit({ niche })} searchPlaceholder="Search niches…" />
            <FilterCombobox label="Offer" icon={<Tag className={iconClassName} />} options={offerOptions} selected={filters.offer} onChange={(offer) => visit({ offer })} searchPlaceholder="Search offers…" />
            <FilterCombobox label="City" icon={<MapPin className={iconClassName} />} options={cityOptions} selected={filters.city} onChange={(city) => visit({ city })} searchPlaceholder="Search cities…" />
            <FilterMenu label="Source" icon={<Database className={iconClassName} />} options={sourceOptions} selected={filters.source} onChange={(source) => visit({ source })} />
        </>
    );

    return (
        <>
            <Head title="Leads" />

            {/* The panel spans the filter bar and the table, like the card in the reference. */}
            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div data-keeps-panel className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4">
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
                        <FilterMenu label="Status" icon={<Tag className={iconClassName} />} options={statusOptions} selected={filters.status} onChange={(status) => visit({ status })} />
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
                        leads={leads.data}
                        total={total}
                        niches={niches}
                        offers={offers}
                        selectedId={selectedId}
                        onSelect={select}
                    />
                </div>
                <LeadPanel
                    lead={panelLead ? { ...panelLead, notes: thread?.notes ?? [] } : null}
                    niche={niches.find((niche) => niche.id === panelLead?.niche_id)}
                    offer={offers.find((offer) => offer.id === panelLead?.offer_id)}
                    niches={niches}
                    offers={offers}
                    messages={thread?.messages ?? []}
                    mailboxes={mailboxes}
                    steps={steps}
                    open={selectedId !== null}
                    onClose={() => setSelectedId(null)}
                />
            </div>
        </>
    );
}

/** The topbar "+ Lead" lives in the static layout config, so it reads the lists off the page itself. */
function NewLeadAction() {
    const { niches, offers } = usePage<PageProps>().props;

    return <LeadDialog niches={niches} offers={offers} />;
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: leadsIndex(), icon: Users }],
    actions: <NewLeadAction />,
};
