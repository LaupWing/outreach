import { Head, usePage } from '@inertiajs/react';
import { SlidersHorizontal, Tag, Target } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { NicheDialog } from '@/components/niche-dialog';
import { NichePanel } from '@/components/niche-panel';
import { nicheStatuses } from '@/components/niche-status-badge';
import { NichesTable, type NicheCounts } from '@/components/niches-table';
import { index as nichesIndex } from '@/routes/niches';
import type { Lead, Message, Niche, NicheStatus, Offer } from '@/types';

type PageProps = {
    niches: Niche[];
    leads: Lead[];
    messages: Message[];
    offers: Offer[];
};

const statusOptions: FilterOption[] = (
    Object.keys(nicheStatuses) as NicheStatus[]
).map((value) => ({ value, label: nicheStatuses[value].label }));

export default function NichesIndex() {
    const { niches: allNiches, leads, messages, offers } = usePage<PageProps>().props;
    const [statuses, setStatuses] = useState<string[]>([]);
    // Search deep-links here with ?niche=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('niche');
    const linked = allNiches.find((item) => String(item.id) === linkedId) ?? null;

    // Ids, not objects: the rows come from props and change under the panel after every save.
    const [selectedId, setSelectedId] = useState<number | null>(linked?.id ?? null);

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelectedId(linked.id);
            setPanelId(linked.id);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps
    // Keeps the last niche while the panel slides shut.
    const [panelId, setPanelId] = useState<number | null>(linked?.id ?? null);
    const panelNiche = allNiches.find((niche) => niche.id === panelId) ?? null;

    // Leads that got at least one mail out, keyed by niche; a lead's status alone loses this once it replies.
    const emailedLeadIds = new Set(
        messages
            .filter((message) => message.sent_at !== null)
            .map((message) => message.lead_id),
    );

    const counts: Record<number, NicheCounts> = Object.fromEntries(
        allNiches.map((niche) => {
            const nicheLeads = leads.filter((lead) => lead.niche_id === niche.id);

            return [
                niche.id,
                {
                    leads: nicheLeads.length,
                    emailed: nicheLeads.filter((lead) => emailedLeadIds.has(lead.id))
                        .length,
                    replied: nicheLeads.filter((lead) => lead.status === 'replied')
                        .length,
                    offers: offers.filter((offer) => offer.niche_id === niche.id)
                        .length,
                },
            ];
        }),
    );

    const niches = allNiches.filter(
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
                        selectedId={selectedId}
                        onSelect={(niche) => {
                            setSelectedId(niche.id);
                            setPanelId(niche.id);
                        }}
                    />
                </div>
                <NichePanel
                    niche={panelNiche}
                    offers={offers.filter((offer) => offer.niche_id === panelNiche?.id)}
                    leads={leads.filter((lead) => lead.niche_id === panelNiche?.id)}
                    emailed={panelNiche ? counts[panelNiche.id].emailed : 0}
                    open={selectedId !== null && panelNiche !== null}
                    onClose={() => setSelectedId(null)}
                />
            </div>
        </>
    );
}

NichesIndex.layout = {
    breadcrumbs: [{ title: 'Niches', href: nichesIndex(), icon: Target }],
    actions: <NicheDialog />,
};
