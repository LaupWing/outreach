import { Head } from '@inertiajs/react';
import {
    Database,
    MapPin,
    Plus,
    SlidersHorizontal,
    Tag,
    Target,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { FilterCombobox } from '@/components/filters/filter-combobox';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { leadStatuses } from '@/components/lead-status-badge';
import { LeadPanel } from '@/components/lead-panel';
import { LeadsTable } from '@/components/leads-table';
import { Button } from '@/components/ui/button';
import { mockLeads } from '@/mock/leads';
import { mockMailboxes } from '@/mock/mailboxes';
import { mockMessages } from '@/mock/messages';
import { mockNiches } from '@/mock/niches';
import { mockOffers } from '@/mock/offers';
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

const cityOptions: FilterOption[] = [
    ...new Set(mockLeads.flatMap((lead) => (lead.city ? [lead.city] : []))),
]
    .sort()
    .map((city) => ({ value: city, label: city }));

const iconClassName = 'size-3.5 text-muted-foreground';

export default function LeadsIndex() {
    const [statuses, setStatuses] = useState<string[]>([]);
    const [sources, setSources] = useState<string[]>([]);
    const [niches, setNiches] = useState<string[]>([]);
    const [cities, setCities] = useState<string[]>([]);
    const [selected, setSelected] = useState<Lead | null>(null);
    // Keeps the last lead while the panel slides shut.
    const [panelLead, setPanelLead] = useState<Lead | null>(null);

    // Client-side for now; these become query parameters once the leads come from Laravel.
    const leads = mockLeads.filter(
        (lead) =>
            (statuses.length === 0 || statuses.includes(lead.status)) &&
            (sources.length === 0 || sources.includes(lead.source)) &&
            (niches.length === 0 || niches.includes(String(lead.niche_id))) &&
            (cities.length === 0 || (lead.city && cities.includes(lead.city))),
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
                        <FilterMenu
                            label="Status"
                            icon={<Tag className={iconClassName} />}
                            options={statusOptions}
                            selected={statuses}
                            onChange={setStatuses}
                        />
                        <FilterCombobox
                            label="Niche"
                            icon={<Target className={iconClassName} />}
                            options={nicheOptions}
                            selected={niches}
                            onChange={setNiches}
                            searchPlaceholder="Search niches…"
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
                    </div>
                    <LeadsTable
                        leads={leads}
                        niches={mockNiches}
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
                    messages={mockMessages.filter((message) => message.lead_id === panelLead?.id)}
                    mailboxes={mockMailboxes}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: leadsIndex(), icon: Users }],
    actions: (
        <Button variant="ghost" size="sm" className="text-muted-foreground">
            <Plus />
            Lead
        </Button>
    ),
};
