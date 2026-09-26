import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CheckCheck,
    Inbox,
    Reply,
} from 'lucide-react';
import { useState } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadPanel } from '@/components/lead-panel';
import { LeadStatusBadge } from '@/components/lead-status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { mockLeads } from '@/mock/leads';
import { mockMailboxes } from '@/mock/mailboxes';
import { mockMessages } from '@/mock/messages';
import { mockNiches } from '@/mock/niches';
import { mockOffers } from '@/mock/offers';
import { index as inboxIndex } from '@/routes/inbox';
import type { Lead, Message } from '@/types';

/** Mock "today"; real data compares against now. */
const TODAY = '2026-09-26';

type Kind = 'reply' | 'due' | 'bounce';

type Item = {
    kind: Kind;
    lead: Lead;
    message: Message | undefined;
    at: string;
};

const kinds: Record<
    Kind,
    { label: string; empty: string; icon: typeof Reply; className: string }
> = {
    reply: {
        label: 'Replies',
        empty: 'No replies waiting.',
        icon: Reply,
        className: 'text-amber-600 dark:text-amber-400',
    },
    due: {
        label: 'Due today',
        empty: 'Nobody is due today.',
        icon: CalendarClock,
        className: 'text-sky-600 dark:text-sky-400',
    },
    bounce: {
        label: 'Bounces',
        empty: 'No bounces.',
        icon: AlertTriangle,
        className: 'text-red-600 dark:text-red-400',
    },
};

const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

const lastMessageOf = (lead: Lead) =>
    mockMessages.filter((message) => message.lead_id === lead.id).at(-1);

// The three queues: replies to answer, follow-ups the scheduler put on today, and bounces to fix.
const items: Item[] = [
    ...mockLeads
        .filter((lead) => lead.status === 'replied')
        .map((lead) => {
            const message = lastMessageOf(lead);

            return {
                kind: 'reply' as const,
                lead,
                message,
                at: message?.reply?.received_at ?? lead.last_contact_at ?? '',
            };
        }),
    ...mockLeads
        .filter(
            (lead) =>
                lead.next_action_at !== null &&
                lead.next_action_at.slice(0, 10) <= TODAY,
        )
        .map((lead) => ({
            kind: 'due' as const,
            lead,
            message: lastMessageOf(lead),
            at: lead.next_action_at ?? '',
        })),
    ...mockLeads
        .filter((lead) => lead.status === 'undeliverable')
        .map((lead) => {
            const message = lastMessageOf(lead);

            return {
                kind: 'bounce' as const,
                lead,
                message,
                at: message?.sent_at ?? lead.last_contact_at ?? '',
            };
        }),
];

export default function InboxIndex() {
    const [kind, setKind] = useState<Kind | null>(null);
    const [selected, setSelected] = useState<Lead | null>(null);
    // Keeps the last lead while the panel slides shut.
    const [panelLead, setPanelLead] = useState<Lead | null>(null);

    const shown = (Object.keys(kinds) as Kind[]).filter(
        (key) => kind === null || key === kind,
    );

    const select = (lead: Lead) => {
        setSelected(lead);
        setPanelLead(lead);
    };

    return (
        <>
            <Head title="Inbox" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    {/* Counts double as the filter, like the pipeline in the sidebar of a CRM. */}
                    <div className="flex h-12 shrink-0 items-center gap-1 overflow-x-auto border-b border-border px-4">
                        <Chip active={kind === null} onClick={() => setKind(null)}>
                            All ({items.length})
                        </Chip>
                        {(Object.keys(kinds) as Kind[]).map((key) => (
                            <Chip
                                key={key}
                                active={kind === key}
                                onClick={() => setKind(key)}
                            >
                                {kinds[key].label} (
                                {items.filter((item) => item.kind === key).length})
                            </Chip>
                        ))}
                    </div>

                    <div className="min-h-0 flex-1 overflow-auto">
                        {items.length === 0 && (
                            <Done />
                        )}
                        {shown.map((key) => {
                            const group = items
                                .filter((item) => item.kind === key)
                                .sort((a, b) => b.at.localeCompare(a.at));
                            const meta = kinds[key];

                            return (
                                <section key={key}>
                                    <h2 className="sticky top-0 z-10 flex h-9 items-center gap-2 border-b border-border bg-background px-4 text-xs text-muted-foreground uppercase">
                                        <meta.icon className={cn('size-3.5', meta.className)} />
                                        {meta.label}
                                        <span className="tabular-nums">{group.length}</span>
                                    </h2>
                                    {group.length === 0 ? (
                                        <p className="border-b border-border px-4 py-3 text-sm text-muted-foreground/60">
                                            {meta.empty}
                                        </p>
                                    ) : (
                                        <ul>
                                            {group.map((item) => (
                                                <Row
                                                    key={`${key}-${item.lead.id}`}
                                                    item={item}
                                                    selected={item.lead.id === selected?.id}
                                                    onSelect={() => select(item.lead)}
                                                />
                                            ))}
                                        </ul>
                                    )}
                                </section>
                            );
                        })}
                    </div>

                    <div className="flex h-14 shrink-0 items-center gap-6 border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground tabular-nums">
                        <span>{items.length} waiting on you</span>
                    </div>
                </div>

                <LeadPanel
                    lead={panelLead}
                    niche={mockNiches.find((niche) => niche.id === panelLead?.niche_id)}
                    offer={mockOffers.find((offer) => offer.id === panelLead?.offer_id)}
                    messages={mockMessages.filter((message) => message.lead_id === panelLead?.id)}
                    mailboxes={mockMailboxes}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                    initialTab="messages"
                />
            </div>
        </>
    );
}

function Row({
    item,
    selected,
    onSelect,
}: {
    item: Item;
    selected: boolean;
    onSelect: () => void;
}) {
    const { lead, message, kind } = item;

    // One line that says why this lead is here.
    const summary =
        kind === 'reply'
            ? message?.reply?.body
            : kind === 'due'
              ? `Step ${(message?.step ?? 0) + 1} is due`
              : `${lead.email ?? 'Address'} bounced`;

    return (
        <li>
            <button
                type="button"
                onClick={onSelect}
                aria-selected={selected}
                className="flex w-full items-center gap-3 border-b border-border px-4 py-3 text-left text-sm transition-colors hover:bg-accent/60 aria-selected:bg-accent"
            >
                <CompanyAvatar name={lead.company} className="size-8 text-xs" />
                <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2">
                        <span className="truncate font-medium">{lead.company}</span>
                        <LeadStatusBadge status={lead.status} className="shrink-0" />
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                        {summary}
                    </span>
                </span>
                <span className="w-24 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                    {item.at ? dateTime.format(new Date(item.at)) : '—'}
                </span>
            </button>
        </li>
    );
}

function Chip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'shrink-0 rounded-md border px-2 py-1 text-xs whitespace-nowrap transition-colors',
                active
                    ? 'border-(--raised-border) bg-accent text-foreground shadow-(--raised-shadow)'
                    : 'border-transparent text-muted-foreground hover:bg-accent/60 hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function Done() {
    return (
        <div className="flex flex-col items-center gap-2 p-12 text-center">
            <CheckCheck className="size-6 text-green-600 dark:text-green-400" />
            <p className="text-sm">Inbox zero.</p>
            <p className="text-xs text-muted-foreground">
                Nothing waits on you. Start a scrape or write the next step.
            </p>
            <Button variant="outline" size="sm" className="mt-2">
                Go to leads
            </Button>
        </div>
    );
}

InboxIndex.layout = {
    breadcrumbs: [{ title: 'Inbox', href: inboxIndex(), icon: Inbox }],
};
