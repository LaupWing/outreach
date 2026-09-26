import { Head, usePage } from '@inertiajs/react';
import { SlidersHorizontal, Tag, Target } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { NicheDialog } from '@/components/niche-dialog';
import { NichePanel } from '@/components/niche-panel';
import { nicheStatuses } from '@/components/niche-status-badge';
import { NichesTable, type NicheCounts } from '@/components/niches-table';
import { mockLeads } from '@/mock/leads';
import { mockMessages } from '@/mock/messages';
import { mockNiches } from '@/mock/niches';
import { mockOffers } from '@/mock/offers';
import { index as nichesIndex } from '@/routes/niches';
import type { Niche, NicheStatus } from '@/types';

const statusOptions: FilterOption[] = (
    Object.keys(nicheStatuses) as NicheStatus[]
).map((value) => ({ value, label: nicheStatuses[value].label }));

// Leads that got at least one mail out, keyed by niche; a lead's status alone loses this once it replies.
const emailedLeadIds = new Set(
    mockMessages
        .filter((message) => message.sent_at !== null)
        .map((message) => message.lead_id),
);

const counts: Record<number, NicheCounts> = Object.fromEntries(
    mockNiches.map((niche) => {
        const leads = mockLeads.filter((lead) => lead.niche_id === niche.id);

        return [
            niche.id,
            {
                leads: leads.length,
                emailed: leads.filter((lead) => emailedLeadIds.has(lead.id))
                    .length,
                replied: leads.filter((lead) => lead.status === 'replied')
                    .length,
                offers: mockOffers.filter(
                    (offer) => offer.niche_id === niche.id,
                ).length,
            },
        ];
    }),
);

export default function NichesIndex() {
    const [statuses, setStatuses] = useState<string[]>([]);
    // Search deep-links here with ?niche=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('niche');
    const linked = mockNiches.find((item) => String(item.id) === linkedId) ?? null;

    const [selected, setSelected] = useState<Niche | null>(linked);

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelected(linked);
            setSelected(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps
    // Keeps the last niche while the panel slides shut.
    const [panelNiche, setPanelNiche] = useState<Niche | null>(linked);

    const niches = mockNiches.filter(
        (niche) => statuses.length === 0 || statuses.includes(niche.status),
    );

    return (
        <>
            <Head title="Niches" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div data-keeps-panel className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4">
                        <span className="mr-1 flex shrink-0 items-center gap-2 text-sm whitespace-nowrap text-muted-foreground">
                            <SlidersHorizontal className="size-4" />
                            Filters:
                        </span>
                        <FilterMenu
                            label="Status"
                            icon={<Tag className="size-3.5 text-muted-foreground" />}
                            options={statusOptions}
                            selected={statuses}
                            onChange={setStatuses}
                        />
                    </div>
                    <NichesTable
                        niches={niches}
                        counts={counts}
                        selectedId={selected?.id ?? null}
                        onSelect={(niche) => {
                            setSelected(niche);
                            setPanelNiche(niche);
                        }}
                    />
                </div>
                <NichePanel
                    niche={panelNiche}
                    offers={mockOffers.filter((offer) => offer.niche_id === panelNiche?.id)}
                    leads={mockLeads.filter((lead) => lead.niche_id === panelNiche?.id)}
                    emailed={panelNiche ? counts[panelNiche.id].emailed : 0}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

NichesIndex.layout = {
    breadcrumbs: [{ title: 'Niches', href: nichesIndex(), icon: Target }],
    actions: <NicheDialog />,
};
