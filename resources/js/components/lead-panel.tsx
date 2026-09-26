import {
    Activity,
    Calendar,
    Database,
    FileText,
    ExternalLink,
    Globe,
    Mail,
    MapPin,
    MessageSquare,
    Phone,
    Radar,
    Send,
    StickyNote,
    Tag,
    Target,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadActivity } from '@/components/lead-activity';
import { LeadMessages } from '@/components/lead-messages';
import { LeadStatusBadge } from '@/components/lead-status-badge';
import {
    SidePanel,
    SidePanelEmpty,
    SidePanelHeader,
    SidePanelRow,
    SidePanelTabs,
} from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import type { Lead, Mailbox, Message, Niche, Offer } from '@/types';

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

/** The lead card on the right: header, actions and the tabs below. */
export function LeadPanel({
    lead,
    niche,
    offer,
    messages,
    mailboxes,
    open,
    onClose,
}: {
    lead: Lead | null;
    niche: Niche | undefined;
    offer: Offer | undefined;
    messages: Message[];
    mailboxes: Mailbox[];
    open: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<Tab>('details');

    return (
        <SidePanel open={open}>
            {lead && (
                <>
                    <SidePanelHeader
                        parent="Leads"
                        title={lead.company}
                        onClose={onClose}
                    />

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
                                        {lead.website}
                                        <ExternalLink className="size-3.5" />
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

                    <SidePanelTabs
                        tabs={tabs.map((item) => ({
                            ...item,
                            badge: item.key === 'messages' ? messages.length : undefined,
                        }))}
                        value={tab}
                        onChange={setTab}
                    />

                    <div className="min-h-0 flex-1 overflow-auto">
                        {tab === 'details' && (
                            <dl className="flex flex-col py-2">
                                <SidePanelRow icon={Mail} label="Email">
                                    {lead.email ?? <SidePanelEmpty>No email found</SidePanelEmpty>}
                                </SidePanelRow>
                                <SidePanelRow icon={Phone} label="Phone">
                                    {lead.phone ?? <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={Globe} label="Website">
                                    {lead.website ? (
                                        <a
                                            href={`https://${lead.website}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 hover:underline"
                                        >
                                            {lead.website}
                                            <ExternalLink className="size-3.5 text-muted-foreground" />
                                        </a>
                                    ) : (
                                        <SidePanelEmpty />
                                    )}
                                </SidePanelRow>
                                <SidePanelRow icon={MapPin} label="City">
                                    {lead.city ?? <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={Target} label="Niche">
                                    {niche?.name ?? <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={Tag} label="Offer">
                                    {offer?.name ?? <SidePanelEmpty>No offer yet</SidePanelEmpty>}
                                </SidePanelRow>
                                <SidePanelRow icon={Database} label="Source">
                                    {sourceLabels[lead.source]}
                                </SidePanelRow>
                                <SidePanelRow icon={Send} label="Sent from">
                                    {mailboxes.find(
                                        (mailbox) =>
                                            mailbox.id ===
                                            messages.at(-1)?.mailbox_id,
                                    )?.address ?? <SidePanelEmpty>Not mailed yet</SidePanelEmpty>}
                                </SidePanelRow>
                                <SidePanelRow icon={Calendar} label="Last contact">
                                    {formatDate(lead.last_contact_at) ?? (
                                        <SidePanelEmpty>Not contacted</SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                                <SidePanelRow icon={Calendar} label="Next action">
                                    {formatDate(lead.next_action_at) ?? (
                                        <SidePanelEmpty>Nothing planned</SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                            </dl>
                        )}

                        {tab === 'signals' && <Signals lead={lead} />}

                        {tab === 'messages' && (
                            <LeadMessages
                                messages={messages}
                                mailboxes={mailboxes}
                            />
                        )}

                        {tab === 'activity' && (
                            <LeadActivity lead={lead} messages={messages} />
                        )}
                    </div>
                </>
            )}
        </SidePanel>
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
                    {lead.hook ?? <SidePanelEmpty>No hook written yet</SidePanelEmpty>}
                </p>
            </div>

            {(signals.blocked || signals.javascript_only) && (
                <div className="rounded-md border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">
                    {signals.blocked
                        ? 'This site blocked the scraper.'
                        : 'This site renders with JavaScript; only the basics were read.'}
                </div>
            )}

            <dl className="-mx-5 flex flex-col">
                <SidePanelRow icon={Calendar} label="Copyright year">
                    {signals.copyright_year ?? <SidePanelEmpty />}
                </SidePanelRow>
                <SidePanelRow icon={Globe} label="Mobile viewport">
                    {signals.viewport === null ? (
                        <SidePanelEmpty />
                    ) : signals.viewport ? (
                        'Yes'
                    ) : (
                        <span className="text-amber-600 dark:text-amber-400">Missing</span>
                    )}
                </SidePanelRow>
                <SidePanelRow icon={Database} label="Software">
                    {signals.software.length > 0 ? (
                        signals.software.join(', ')
                    ) : (
                        <SidePanelEmpty />
                    )}
                </SidePanelRow>
                <SidePanelRow icon={FileText} label="Last news">
                    {formatDate(signals.last_news_at) ?? <SidePanelEmpty>No news found</SidePanelEmpty>}
                </SidePanelRow>
            </dl>
        </div>
    );
}

function Placeholder({ children }: { children: ReactNode }) {
    return (
        <p className="p-5 text-sm text-muted-foreground">{children}</p>
    );
}
