import { Head, usePage } from '@inertiajs/react';
import { SlidersHorizontal, Tag, Target } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterCombobox } from '@/components/filters/filter-combobox';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { OfferPanel } from '@/components/offer-panel';
import { offerStatuses } from '@/components/offer-status-badge';
import { OffersTable, type OfferCounts } from '@/components/offers-table';
import { OfferDialog } from '@/components/offer-dialog';
import { mockLeads } from '@/mock/leads';
import { mockMessages } from '@/mock/messages';
import { mockNiches } from '@/mock/niches';
import { mockOffers } from '@/mock/offers';
import { mockSequenceSteps } from '@/mock/sequence-steps';
import { index as offersIndex } from '@/routes/offers';
import type { Offer, OfferStatus } from '@/types';

const statusOptions: FilterOption[] = (
    Object.keys(offerStatuses) as OfferStatus[]
).map((value) => ({ value, label: offerStatuses[value].label }));

const nicheOptions: FilterOption[] = mockNiches.map((niche) => ({
    value: String(niche.id),
    label: niche.name,
}));

// Leads that got at least one mail out; a lead's status alone loses this once it replies.
const emailedLeadIds = new Set(
    mockMessages
        .filter((message) => message.sent_at !== null)
        .map((message) => message.lead_id),
);

const counts: Record<number, OfferCounts> = Object.fromEntries(
    mockOffers.map((offer) => {
        const leads = mockLeads.filter((lead) => lead.offer_id === offer.id);

        return [
            offer.id,
            {
                steps: mockSequenceSteps.filter(
                    (step) => step.offer_id === offer.id,
                ).length,
                leads: leads.length,
                emailed: leads.filter((lead) => emailedLeadIds.has(lead.id))
                    .length,
                replied: leads.filter((lead) => lead.status === 'replied')
                    .length,
            },
        ];
    }),
);

const iconClassName = 'size-3.5 text-muted-foreground';

export default function OffersIndex() {
    const [statuses, setStatuses] = useState<string[]>([]);
    const [niches, setNiches] = useState<string[]>([]);
    // Search deep-links here with ?offer=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('offer');
    const linked = mockOffers.find((item) => String(item.id) === linkedId) ?? null;

    const [selected, setSelected] = useState<Offer | null>(linked);

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelected(linked);
            setSelected(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps
    // Keeps the last offer while the panel slides shut.
    const [panelOffer, setPanelOffer] = useState<Offer | null>(linked);

    const offers = mockOffers.filter(
        (offer) =>
            (statuses.length === 0 || statuses.includes(offer.status)) &&
            (niches.length === 0 || niches.includes(String(offer.niche_id))),
    );

    return (
        <>
            <Head title="Offers" />

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
                    </div>
                    <OffersTable
                        offers={offers}
                        niches={mockNiches}
                        counts={counts}
                        selectedId={selected?.id ?? null}
                        onSelect={(offer) => {
                            setSelected(offer);
                            setPanelOffer(offer);
                        }}
                    />
                </div>
                <OfferPanel
                    offer={panelOffer}
                    niche={mockNiches.find((niche) => niche.id === panelOffer?.niche_id)}
                    niches={mockNiches}
                    steps={mockSequenceSteps.filter((step) => step.offer_id === panelOffer?.id)}
                    leads={mockLeads.filter((lead) => lead.offer_id === panelOffer?.id)}
                    emailed={panelOffer ? counts[panelOffer.id].emailed : 0}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

OffersIndex.layout = {
    breadcrumbs: [{ title: 'Offers', href: offersIndex(), icon: Tag }],
    actions: <OfferDialog niches={mockNiches} />,
};
