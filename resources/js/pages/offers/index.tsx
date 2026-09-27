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
import { index as offersIndex } from '@/routes/offers';
import type {
    Lead,
    Message,
    Niche,
    Offer,
    OfferStatus,
    SequenceStep,
} from '@/types';

type PageProps = {
    offers: Offer[];
    niches: Niche[];
    leads: Lead[];
    messages: Message[];
    /** Every sequence step, ordered by offer and step. */
    steps: SequenceStep[];
};

const statusOptions: FilterOption[] = (
    Object.keys(offerStatuses) as OfferStatus[]
).map((value) => ({ value, label: offerStatuses[value].label }));

const iconClassName = 'size-3.5 text-muted-foreground';

export default function OffersIndex() {
    const {
        offers: allOffers,
        niches: allNiches,
        leads,
        messages,
        steps,
    } = usePage<PageProps>().props;
    const [statuses, setStatuses] = useState<string[]>([]);
    const [niches, setNiches] = useState<string[]>([]);
    // Search deep-links here with ?offer=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('offer');
    const linked =
        allOffers.find((item) => String(item.id) === linkedId) ?? null;

    // Ids, not objects: the rows come from props and change under the panel after every save.
    const [selectedId, setSelectedId] = useState<number | null>(
        linked?.id ?? null,
    );

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelectedId(linked.id);
            setPanelId(linked.id);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps
    // Keeps the last offer while the panel slides shut.
    const [panelId, setPanelId] = useState<number | null>(linked?.id ?? null);
    const panelOffer = allOffers.find((offer) => offer.id === panelId) ?? null;

    const nicheOptions: FilterOption[] = allNiches.map((niche) => ({
        value: String(niche.id),
        label: niche.name,
    }));

    // Leads that got at least one mail out; a lead's status alone loses this once it replies.
    const emailedLeadIds = new Set(
        messages
            .filter((message) => message.sent_at !== null)
            .map((message) => message.lead_id),
    );

    const counts: Record<number, OfferCounts> = Object.fromEntries(
        allOffers.map((offer) => {
            const offerLeads = leads.filter(
                (lead) => lead.offer_id === offer.id,
            );

            return [
                offer.id,
                {
                    steps: steps.filter((step) => step.offer_id === offer.id)
                        .length,
                    leads: offerLeads.length,
                    emailed: offerLeads.filter((lead) =>
                        emailedLeadIds.has(lead.id),
                    ).length,
                    replied: offerLeads.filter(
                        (lead) => lead.status === 'replied',
                    ).length,
                },
            ];
        }),
    );

    const offers = allOffers.filter(
        (offer) =>
            (statuses.length === 0 || statuses.includes(offer.status)) &&
            (niches.length === 0 || niches.includes(String(offer.niche_id))),
    );

    return (
        <>
            <Head title="Offers" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div
                        data-keeps-panel
                        className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4"
                    >
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
                        niches={allNiches}
                        counts={counts}
                        selectedId={selectedId}
                        onSelect={(offer) => {
                            setSelectedId(offer.id);
                            setPanelId(offer.id);
                        }}
                    />
                </div>
                <OfferPanel
                    offer={panelOffer}
                    niche={allNiches.find(
                        (niche) => niche.id === panelOffer?.niche_id,
                    )}
                    niches={allNiches}
                    steps={steps.filter(
                        (step) => step.offer_id === panelOffer?.id,
                    )}
                    leads={leads.filter(
                        (lead) => lead.offer_id === panelOffer?.id,
                    )}
                    emailed={panelOffer ? counts[panelOffer.id].emailed : 0}
                    open={selectedId !== null && panelOffer !== null}
                    onClose={() => setSelectedId(null)}
                />
            </div>
        </>
    );
}

OffersIndex.layout = {
    breadcrumbs: [{ title: 'Offers', href: offersIndex(), icon: Tag }],
    // The dialog reads the niches from the page props itself; the layout is static.
    actions: <OfferDialog />,
};
