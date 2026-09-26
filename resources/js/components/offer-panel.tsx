import { router } from '@inertiajs/react';
import {
    Clock,
    FileText,
    ListOrdered,
    Pencil,
    Play,
    Square,
    Tag,
    Target,
    Trash2,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadStatusBadge } from '@/components/lead-status-badge';
import { OfferDialog } from '@/components/offer-dialog';
import { OfferStatusBadge } from '@/components/offer-status-badge';
import { SequenceEditor } from '@/components/sequence-editor';
import {
    SidePanel,
    SidePanelEmpty,
    SidePanelHeader,
    SidePanelRow,
    SidePanelStat,
    SidePanelTabs,
} from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { destroy, update } from '@/routes/offers';
import type { Lead, Niche, Offer, OfferStatus, SequenceStep } from '@/types';

const tabs = [
    { key: 'sequence', label: 'Sequence', icon: ListOrdered },
    { key: 'overview', label: 'Overview', icon: FileText },
    { key: 'leads', label: 'Leads', icon: Users },
] as const;

type Tab = (typeof tabs)[number]['key'];

/** The offer card: the mail sequence first, since that is what an offer is. */
export function OfferPanel({
    offer,
    niche,
    niches = [],
    steps,
    leads,
    emailed,
    open,
    onClose,
}: {
    offer: Offer | null;
    niche: Niche | undefined;
    /** Every niche, for the select in the edit dialog. */
    niches?: Niche[];
    steps: SequenceStep[];
    leads: Lead[];
    emailed: number;
    open: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<Tab>('sequence');
    // Delete asks twice: the second click within a few seconds does it.
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    useEffect(() => {
        if (!confirmingDelete) {
            return;
        }

        const timer = window.setTimeout(() => setConfirmingDelete(false), 3000);

        return () => window.clearTimeout(timer);
    }, [confirmingDelete]);

    useEffect(() => {
        setConfirmingDelete(false);
    }, [offer?.id]);

    const setStatus = (status: OfferStatus) => {
        if (offer) {
            router.patch(update.url(offer.id), { status }, { preserveScroll: true });
        }
    };

    const remove = () => {
        if (!offer) {
            return;
        }

        if (!confirmingDelete) {
            setConfirmingDelete(true);

            return;
        }

        router.delete(destroy.url(offer.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const replied = leads.filter((lead) => lead.status === 'replied').length;
    const rate = emailed > 0 ? `${Math.round((replied / emailed) * 100)}%` : undefined;
    const totalDays = steps.reduce((sum, step) => sum + step.days_after_previous, 0);

    return (
        <SidePanel open={open} onClose={onClose}>
            {offer && (
                <>
                    <SidePanelHeader
                        parent="Offers"
                        title={offer.name}
                        onClose={onClose}
                    />

                    <div className="flex flex-col gap-4 px-5 pt-5 pb-4">
                        <div className="flex items-center gap-3">
                            <CompanyAvatar
                                name={offer.name}
                                className="size-12 rounded-lg text-base"
                            />
                            <div className="min-w-0">
                                <h2 className="truncate text-lg font-semibold tracking-tight">
                                    {offer.name}
                                </h2>
                                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Target className="size-3.5" />
                                    {niche?.name ?? 'No niche'}
                                </span>
                            </div>
                            <OfferStatusBadge
                                status={offer.status}
                                className="ml-auto shrink-0"
                            />
                        </div>

                        <div className="grid grid-cols-3 gap-2">
                            <SidePanelStat
                                label="Steps"
                                value={steps.length}
                                hint={totalDays > 0 ? `${totalDays} days` : undefined}
                            />
                            <SidePanelStat label="Emailed" value={emailed} />
                            <SidePanelStat
                                label="Replied"
                                value={replied}
                                hint={rate}
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <OfferDialog
                                offer={offer}
                                niches={niches}
                                trigger={
                                    <Button variant="outline" size="sm">
                                        <Pencil />
                                        Edit
                                    </Button>
                                }
                            />
                            {offer.status === 'active' ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setStatus('stopped')}
                                >
                                    <Square />
                                    Stop
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setStatus('active')}
                                >
                                    <Play />
                                    Activate
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-auto text-red-600 hover:text-red-600 dark:text-red-400 dark:hover:text-red-400"
                                onClick={remove}
                            >
                                <Trash2 />
                                {confirmingDelete ? 'Really delete?' : 'Delete'}
                            </Button>
                        </div>
                    </div>

                    <SidePanelTabs
                        tabs={tabs.map((item) => ({
                            ...item,
                            badge:
                                item.key === 'leads'
                                    ? leads.length
                                    : item.key === 'sequence'
                                      ? steps.length
                                      : undefined,
                        }))}
                        value={tab}
                        onChange={setTab}
                    />

                    <div className="min-h-0 flex-1 overflow-auto">
                        {tab === 'sequence' && (
                            <SequenceEditor key={offer.id} offerId={offer.id} steps={steps} />
                        )}

                        {tab === 'overview' && (
                            <div className="flex flex-col gap-4 p-5">
                                <div>
                                    <div className="mb-1 text-xs text-muted-foreground uppercase">
                                        Description
                                    </div>
                                    <p className="text-sm text-foreground/80">
                                        {offer.description ?? (
                                            <SidePanelEmpty>
                                                No description yet
                                            </SidePanelEmpty>
                                        )}
                                    </p>
                                </div>
                                <dl className="-mx-5 flex flex-col">
                                    <SidePanelRow icon={Target} label="Niche">
                                        {niche?.name ?? <SidePanelEmpty />}
                                    </SidePanelRow>
                                    <SidePanelRow icon={Tag} label="Status">
                                        <OfferStatusBadge status={offer.status} />
                                    </SidePanelRow>
                                    <SidePanelRow icon={Clock} label="Sequence">
                                        {steps.length > 0 ? (
                                            `${steps.length} mails over ${totalDays} days`
                                        ) : (
                                            <SidePanelEmpty>No steps yet</SidePanelEmpty>
                                        )}
                                    </SidePanelRow>
                                </dl>
                            </div>
                        )}

                        {tab === 'leads' &&
                            (leads.length === 0 ? (
                                <p className="p-5 text-sm text-muted-foreground">
                                    No leads on this offer yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col">
                                    {leads.map((lead) => (
                                        <li
                                            key={lead.id}
                                            className="flex items-center gap-3 border-b border-border px-5 py-2.5 text-sm"
                                        >
                                            <CompanyAvatar name={lead.company} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {lead.company}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {lead.email ?? 'No email found'}
                                                </span>
                                            </span>
                                            <LeadStatusBadge
                                                status={lead.status}
                                                className="shrink-0"
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ))}
                    </div>
                </>
            )}
        </SidePanel>
    );
}
