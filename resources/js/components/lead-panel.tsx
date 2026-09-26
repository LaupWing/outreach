import {
    Activity,
    Calendar,
    Database,
    FileText,
    Globe,
    Link2,
    Mail,
    MapPin,
    MessageSquare,
    Phone,
    Radar,
    StickyNote,
    Tag,
    Target,
    X,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadStatusBadge } from '@/components/lead-status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Lead, Niche, Offer } from '@/types';

const tabs = [
    { key: 'details', label: 'Details', icon: FileText },
    { key: 'signals', label: 'Signals', icon: Radar },
    { key: 'messages', label: 'Messages', icon: MessageSquare },
    { key: 'activity', label: 'Activity', icon: Activity },
] as const;

type Tab = (typeof tabs)[number]['key'];

const sourceLabels = {
    places: 'Google Places',
    register: 'Register',
    manual: 'Manual',
} as const;

const longDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const formatDate = (value: string | null) =>
    value ? longDate.format(new Date(value)) : null;

/**
 * The detail panel on the right, like the customer card in the reference.
 * It stays mounted and slides open and closed, so the table's width eases with it.
 */
export function LeadPanel({
    lead,
    niche,
    offer,
    open,
    onClose,
}: {
    lead: Lead | null;
    niche: Niche | undefined;
    offer: Offer | undefined;
    open: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<Tab>('details');

    return (
        <div
            className={cn(
                'shrink-0 overflow-hidden transition-[width] duration-300 ease-out',
                open ? 'w-120' : 'w-0',
            )}
            aria-hidden={!open}
        >
            {/* The last lead stays rendered while closing, so the content does not blink away. */}
            {lead && (
                <aside
                    className={cn(
                        'flex h-full w-120 flex-col border-l border-border bg-background transition-transform duration-300 ease-out',
                        open ? 'translate-x-0' : 'translate-x-full',
                    )}
                >
                    <div className="flex h-12 shrink-0 items-center gap-2 border-b border-border px-4 text-sm">
                        <span className="shrink-0 text-muted-foreground">Leads</span>
                        <span className="shrink-0 text-muted-foreground">/</span>
                        <span className="truncate">{lead.company}</span>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="ml-auto size-7 shrink-0 text-muted-foreground"
                            onClick={onClose}
                            aria-label="Close"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>

                    <div className="flex flex-col gap-4 px-5 pt-5 pb-4">
                        <div className="flex items-center gap-3">
                            <CompanyAvatar
                                name={lead.company}
                                className="size-12 rounded-lg text-base"
                            />
                            <div className="min-w-0">
                                <h2 className="truncate text-lg font-semibold tracking-tight">
                                    {lead.company}
                                </h2>
                                {lead.website && (
                                    <a
                                        href={`https://${lead.website}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                                    >
                                        <Link2 className="size-3.5" />
                                        {lead.website}
                                    </a>
                                )}
                            </div>
                            <LeadStatusBadge status={lead.status} className="ml-auto shrink-0" />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" disabled={!lead.email}>
                                <Mail />
                                Email
                            </Button>
                            <Button variant="outline" size="sm">
                                <StickyNote />
                                Add note
                            </Button>
                        </div>
                    </div>

                    <div className="flex shrink-0 gap-1 border-b border-border px-3">
                        {tabs.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => setTab(item.key)}
                                className={cn(
                                    '-mb-px flex items-center gap-1.5 border-b-2 px-2 py-2.5 text-sm transition-colors',
                                    tab === item.key
                                        ? 'border-foreground text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <item.icon className="size-4 shrink-0" />
                                {item.label}
                            </button>
                        ))}
                    </div>

                    <div className="min-h-0 flex-1 overflow-auto">
                        {tab === 'details' && (
                            <dl className="flex flex-col py-2">
                                <Row icon={Mail} label="Email">
                                    {lead.email ?? <Empty>No email found</Empty>}
                                </Row>
                                <Row icon={Phone} label="Phone">
                                    {lead.phone ?? <Empty />}
                                </Row>
                                <Row icon={Globe} label="Website">
                                    {lead.website ?? <Empty />}
                                </Row>
                                <Row icon={MapPin} label="City">
                                    {lead.city ?? <Empty />}
                                </Row>
                                <Row icon={Target} label="Niche">
                                    {niche?.name ?? <Empty />}
                                </Row>
                                <Row icon={Tag} label="Offer">
                                    {offer?.name ?? <Empty>No offer yet</Empty>}
                                </Row>
                                <Row icon={Database} label="Source">
                                    {sourceLabels[lead.source]}
                                </Row>
                                <Row icon={Calendar} label="Last contact">
                                    {formatDate(lead.last_contact_at) ?? (
                                        <Empty>Not contacted</Empty>
                                    )}
                                </Row>
                                <Row icon={Calendar} label="Next action">
                                    {formatDate(lead.next_action_at) ?? (
                                        <Empty>Nothing planned</Empty>
                                    )}
                                </Row>
                            </dl>
                        )}

                        {tab === 'signals' && <Signals lead={lead} />}

                        {tab === 'messages' && (
                            <Placeholder>No messages yet.</Placeholder>
                        )}

                        {tab === 'activity' && (
                            <Placeholder>No activity yet.</Placeholder>
                        )}
                    </div>
                </aside>
            )}
        </div>
    );
}

function Signals({ lead }: { lead: Lead }) {
    const signals = lead.signals;

    if (!signals) {
        return <Placeholder>Not enriched yet.</Placeholder>;
    }

    return (
        <div className="flex flex-col gap-4 p-5">
            {/* The hook is what the first email opens with, so it sits on top. */}
            <div className="rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow)">
                <div className="mb-1 text-xs text-muted-foreground uppercase">
                    Hook
                </div>
                <p className="text-sm">
                    {lead.hook ?? <Empty>No hook written yet</Empty>}
                </p>
            </div>

            {(signals.blocked || signals.javascript_only) && (
                <div className="rounded-md border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-400">
                    {signals.blocked
                        ? 'This site blocked the scraper.'
                        : 'This site renders with JavaScript; only the basics were read.'}
                </div>
            )}

            <dl className="-mx-5 flex flex-col">
                <Row icon={Calendar} label="Copyright year">
                    {signals.copyright_year ?? <Empty />}
                </Row>
                <Row icon={Globe} label="Mobile viewport">
                    {signals.viewport === null ? (
                        <Empty />
                    ) : signals.viewport ? (
                        'Yes'
                    ) : (
                        <span className="text-amber-400">Missing</span>
                    )}
                </Row>
                <Row icon={Database} label="Software">
                    {signals.software.length > 0 ? (
                        signals.software.join(', ')
                    ) : (
                        <Empty />
                    )}
                </Row>
                <Row icon={FileText} label="Last news">
                    {formatDate(signals.last_news_at) ?? <Empty>No news found</Empty>}
                </Row>
            </dl>
        </div>
    );
}

function Row({
    icon: Icon,
    label,
    children,
}: {
    icon: typeof Mail;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 px-5 py-2.5 text-sm">
            <dt className="flex w-32 shrink-0 items-center gap-2 text-muted-foreground">
                <Icon className="size-4 shrink-0" />
                {label}
            </dt>
            <dd className="min-w-0 truncate">{children}</dd>
        </div>
    );
}

function Empty({ children = '—' }: { children?: ReactNode }) {
    return <span className="text-neutral-600">{children}</span>;
}

function Placeholder({ children }: { children: ReactNode }) {
    return (
        <p className="p-5 text-sm text-muted-foreground">{children}</p>
    );
}
