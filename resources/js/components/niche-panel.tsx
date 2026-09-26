import {
    Check,
    FileText,
    Pencil,
    Tag,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadStatusBadge, leadStatuses } from '@/components/lead-status-badge';
import { NicheStatusBadge, nicheStatuses } from '@/components/niche-status-badge';
import {
    SidePanel,
    SidePanelEmpty,
    SidePanelHeader,
    SidePanelRow,
    SidePanelStat,
    SidePanelTabs,
} from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Lead, LeadStatus, Niche, Offer, OfferStatus } from '@/types';

const tabs = [
    { key: 'overview', label: 'Overview', icon: FileText },
    { key: 'offers', label: 'Offers', icon: Tag },
    { key: 'leads', label: 'Leads', icon: Users },
] as const;

type Tab = (typeof tabs)[number]['key'];

const offerStatuses: Record<OfferStatus, { label: string; className: string }> =
    {
        active: {
            label: 'Active',
            className:
                'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400',
        },
        idea: {
            label: 'Idea',
            className: 'border-border bg-accent text-foreground/80',
        },
        stopped: {
            label: 'Stopped',
            className: 'border-border bg-transparent text-muted-foreground',
        },
    };

/** The niche card: why we try it, what we learned and the offers and leads under it. */
export function NichePanel({
    niche,
    offers,
    leads,
    emailed,
    open,
    onClose,
}: {
    niche: Niche | null;
    offers: Offer[];
    leads: Lead[];
    /** Leads that got at least one mail; the messages know this, the lead does not. */
    emailed: number;
    open: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<Tab>('overview');

    const replied = leads.filter((lead) => lead.status === 'replied').length;
    const perStatus = (Object.keys(leadStatuses) as LeadStatus[])
        .map((status) => ({
            status,
            count: leads.filter((lead) => lead.status === status).length,
        }))
        .filter((item) => item.count > 0);

    return (
        <SidePanel open={open}>
            {niche && (
                <>
                    <SidePanelHeader
                        parent="Niches"
                        title={niche.name}
                        onClose={onClose}
                    />

                    <div className="flex flex-col gap-4 px-5 pt-5 pb-4">
                        <div className="flex items-center gap-3">
                            <CompanyAvatar
                                name={niche.name}
                                className="size-12 rounded-lg text-base"
                            />
                            <div className="min-w-0">
                                <h2 className="truncate text-lg font-semibold tracking-tight">
                                    {niche.name}
                                </h2>
                                <NicheStatusBadge status={niche.status} />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-2">
                            <SidePanelStat label="Leads" value={leads.length} />
                            <SidePanelStat
                                label="Replied"
                                value={replied}
                                hint={
                                    emailed > 0
                                        ? `${Math.round((replied / emailed) * 100)}%`
                                        : undefined
                                }
                            />
                            <SidePanelStat label="Offers" value={offers.length} />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm">
                                <Pencil />
                                Edit
                            </Button>
                            {niche.status !== 'proven' && (
                                <Button variant="outline" size="sm">
                                    <Check />
                                    Mark proven
                                </Button>
                            )}
                            {niche.status !== 'dropped' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-muted-foreground"
                                >
                                    <XCircle />
                                    Drop
                                </Button>
                            )}
                        </div>
                    </div>

                    <SidePanelTabs
                        tabs={tabs.map((item) => ({
                            ...item,
                            badge:
                                item.key === 'offers'
                                    ? offers.length
                                    : item.key === 'leads'
                                      ? leads.length
                                      : undefined,
                        }))}
                        value={tab}
                        onChange={setTab}
                    />

                    <div className="min-h-0 flex-1 overflow-auto">
                        {tab === 'overview' && (
                            <div className="flex flex-col">
                                <TextBlock title="Why">{niche.why}</TextBlock>
                                <TextBlock title="Findings">
                                    {niche.findings}
                                </TextBlock>

                                <dl className="flex flex-col py-2">
                                    <SidePanelRow icon={Tag} label="Status">
                                        {nicheStatuses[niche.status].label}
                                    </SidePanelRow>
                                    {perStatus.length === 0 ? (
                                        <SidePanelRow icon={Users} label="Leads">
                                            <SidePanelEmpty>
                                                None yet
                                            </SidePanelEmpty>
                                        </SidePanelRow>
                                    ) : (
                                        perStatus.map((item) => (
                                            <SidePanelRow
                                                key={item.status}
                                                icon={Users}
                                                label={leadStatuses[item.status].label}
                                            >
                                                <span className="tabular-nums">
                                                    {item.count}
                                                </span>
                                            </SidePanelRow>
                                        ))
                                    )}
                                </dl>
                            </div>
                        )}

                        {tab === 'offers' &&
                            (offers.length === 0 ? (
                                <p className="p-5 text-sm text-muted-foreground">
                                    No offers for this niche yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col">
                                    {offers.map((offer) => {
                                        const status = offerStatuses[offer.status];

                                        return (
                                            <li
                                                key={offer.id}
                                                className="flex flex-col gap-1 border-b border-border px-5 py-3 text-sm"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span className="min-w-0 flex-1 truncate font-medium">
                                                        {offer.name}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'inline-flex shrink-0 items-center rounded-md border px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                                                            status.className,
                                                        )}
                                                    >
                                                        {status.label}
                                                    </span>
                                                </span>
                                                {offer.description ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        {offer.description}
                                                    </span>
                                                ) : (
                                                    <SidePanelEmpty>
                                                        No description
                                                    </SidePanelEmpty>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            ))}

                        {tab === 'leads' &&
                            (leads.length === 0 ? (
                                <p className="p-5 text-sm text-muted-foreground">
                                    No leads in this niche yet.
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

function TextBlock({
    title,
    children,
}: {
    title: string;
    children: string | null;
}) {
    return (
        <div className="flex flex-col gap-1 border-b border-border px-5 py-4">
            <span className="text-xs text-muted-foreground">{title}</span>
            {children ? (
                <p className="text-sm leading-relaxed text-foreground/80">
                    {children}
                </p>
            ) : (
                <SidePanelEmpty>Nothing written yet</SidePanelEmpty>
            )}
        </div>
    );
}
