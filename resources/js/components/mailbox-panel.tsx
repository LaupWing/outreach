import {
    AlertTriangle,
    AtSign,
    FileText,
    Gauge,
    Mail,
    MessageSquare,
    Pause,
    Play,
    Plug,
    Reply,
    Tag,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import type { MailboxCounts } from '@/components/mailboxes-table';
import { mailboxTypes } from '@/components/mailboxes-table';
import { MailboxStatusBadge } from '@/components/mailbox-status-badge';
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
import type { Lead, Mailbox, Message, MessageStatus } from '@/types';

const tabs = [
    { key: 'overview', label: 'Overview', icon: FileText },
    { key: 'messages', label: 'Messages', icon: MessageSquare },
] as const;

type Tab = (typeof tabs)[number]['key'];

const messageTones: Record<MessageStatus, string> = {
    draft: 'text-muted-foreground',
    sent: 'text-sky-600 dark:text-sky-400',
    bounced: 'text-red-600 dark:text-red-400',
    replied: 'text-amber-600 dark:text-amber-400',
};

const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

/** The mailbox card: how much room it has today and whether its mail lands. */
export function MailboxPanel({
    mailbox,
    counts,
    messages,
    leads,
    open,
    onClose,
}: {
    mailbox: Mailbox | null;
    counts: MailboxCounts | undefined;
    messages: Message[];
    leads: Lead[];
    open: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<Tab>('overview');

    const sent = counts?.sent ?? 0;
    const rate = (count: number) =>
        sent > 0 ? `${Math.round((count / sent) * 100)}%` : undefined;
    const share = mailbox
        ? Math.min(mailbox.sent_today / mailbox.daily_limit, 1)
        : 0;
    const companyOf = (leadId: number) =>
        leads.find((lead) => lead.id === leadId)?.company ?? 'Unknown lead';

    return (
        <SidePanel open={open}>
            {mailbox && (
                <>
                    <SidePanelHeader
                        parent="Mailboxes"
                        title={mailbox.address}
                        onClose={onClose}
                    />

                    <div className="flex flex-col gap-4 px-5 pt-5 pb-4">
                        <div className="flex items-center gap-3">
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-sky-400 to-violet-500 text-white">
                                <Mail className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <h2 className="truncate text-lg font-semibold tracking-tight">
                                    {mailbox.address}
                                </h2>
                                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Plug className="size-3.5" />
                                    {mailboxTypes[mailbox.type]}
                                </span>
                            </div>
                            <MailboxStatusBadge
                                status={mailbox.status}
                                className="ml-auto shrink-0"
                            />
                        </div>

                        {/* Today's room, same bar as on the scrape page. */}
                        <div className="flex flex-col gap-2 rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow)">
                            <div className="flex items-baseline justify-between">
                                <span className="text-sm">Sent today</span>
                                <span className="tabular-nums">
                                    <span
                                        className={cn(
                                            'text-2xl font-semibold',
                                            share >= 1
                                                ? 'text-red-600 dark:text-red-400'
                                                : share >= 0.75
                                                  ? 'text-amber-600 dark:text-amber-400'
                                                  : 'lava bg-clip-text text-transparent',
                                        )}
                                    >
                                        {mailbox.sent_today}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {' '}
                                        / {mailbox.daily_limit}
                                    </span>
                                </span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full bg-border">
                                <div
                                    className={cn(
                                        'h-full rounded-full',
                                        share >= 1
                                            ? 'bg-red-500'
                                            : share >= 0.75
                                              ? 'bg-amber-500'
                                              : 'lava',
                                    )}
                                    style={{ width: `${share * 100}%` }}
                                />
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {mailbox.daily_limit - mailbox.sent_today} left
                                today, spread out rather than in one go.
                            </span>
                        </div>

                        <div className="grid grid-cols-3 gap-2">
                            <SidePanelStat label="Sent" value={sent} />
                            <SidePanelStat
                                label="Replies"
                                value={counts?.replied ?? 0}
                                hint={rate(counts?.replied ?? 0)}
                            />
                            <SidePanelStat
                                label="Bounces"
                                value={counts?.bounced ?? 0}
                                hint={rate(counts?.bounced ?? 0)}
                                warn={(counts?.bounced ?? 0) / Math.max(sent, 1) > 0.05}
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            {mailbox.status === 'paused' ? (
                                <Button variant="outline" size="sm">
                                    <Play />
                                    Resume
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm">
                                    <Pause />
                                    Pause
                                </Button>
                            )}
                            <Button variant="outline" size="sm">
                                <Zap />
                                Test connection
                            </Button>
                        </div>
                    </div>

                    <SidePanelTabs
                        tabs={tabs.map((item) => ({
                            ...item,
                            badge:
                                item.key === 'messages'
                                    ? messages.length
                                    : undefined,
                        }))}
                        value={tab}
                        onChange={setTab}
                    />

                    <div className="min-h-0 flex-1 overflow-auto">
                        {tab === 'overview' && (
                            <dl className="flex flex-col py-2">
                                <SidePanelRow icon={AtSign} label="Address">
                                    {mailbox.address}
                                </SidePanelRow>
                                <SidePanelRow icon={Plug} label="Connection">
                                    {mailbox.type === 'gmail'
                                        ? 'Gmail API'
                                        : 'IMAP and SMTP'}
                                </SidePanelRow>
                                <SidePanelRow icon={Tag} label="Status">
                                    <MailboxStatusBadge status={mailbox.status} />
                                </SidePanelRow>
                                <SidePanelRow icon={Gauge} label="Daily limit">
                                    {mailbox.daily_limit} mails
                                </SidePanelRow>
                                <SidePanelRow icon={Reply} label="Reply rate">
                                    {rate(counts?.replied ?? 0) ?? (
                                        <SidePanelEmpty>Nothing sent yet</SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                                <SidePanelRow icon={AlertTriangle} label="Bounce rate">
                                    {rate(counts?.bounced ?? 0) ?? (
                                        <SidePanelEmpty>Nothing sent yet</SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                            </dl>
                        )}

                        {tab === 'messages' &&
                            (messages.length === 0 ? (
                                <p className="p-5 text-sm text-muted-foreground">
                                    Nothing sent from this mailbox yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col">
                                    {messages.map((message) => (
                                        <li
                                            key={message.id}
                                            className="flex items-center gap-3 border-b border-border px-5 py-2.5 text-sm"
                                        >
                                            <CompanyAvatar name={companyOf(message.lead_id)} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {companyOf(message.lead_id)}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    Step {message.step}: {message.subject}
                                                </span>
                                            </span>
                                            <span
                                                className={cn(
                                                    'shrink-0 text-xs capitalize',
                                                    messageTones[message.status],
                                                )}
                                            >
                                                {message.status}
                                            </span>
                                            <span className="w-20 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                                                {message.sent_at
                                                    ? dateTime.format(new Date(message.sent_at))
                                                    : '—'}
                                            </span>
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
