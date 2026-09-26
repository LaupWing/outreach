import {
    AlertTriangle,
    AtSign,
    Gauge,
    Mail,
    Plug,
    Reply,
    Send,
    Tag,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { MailboxStatusBadge } from '@/components/mailbox-status-badge';
import { cn } from '@/lib/utils';
import type { Mailbox } from '@/types';

const columns: { title: string; icon: typeof Mail; className: string }[] = [
    { title: 'Address', icon: AtSign, className: 'w-64' },
    { title: 'Type', icon: Plug, className: 'w-24' },
    { title: 'Status', icon: Tag, className: 'w-32' },
    { title: 'Today', icon: Gauge, className: 'w-56' },
    { title: 'Sent', icon: Send, className: 'w-24' },
    { title: 'Replies', icon: Reply, className: 'w-32' },
    { title: 'Bounces', icon: AlertTriangle, className: 'w-32' },
];

/** What a mailbox row shows beyond its own columns, derived from the messages it sent. */
export type MailboxCounts = {
    sent: number;
    replied: number;
    bounced: number;
};

export const mailboxTypes = { gmail: 'Gmail', imap: 'IMAP' } as const;

function Cell({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <td
            className={cn(
                'h-11 truncate border-r border-b border-border px-4 text-sm last:border-r-0',
                className,
            )}
        >
            {children}
        </td>
    );
}

/** Rate as a percentage of sent, with the colour telling you whether it is good or bad news. */
function Rate({
    count,
    sent,
    warnAbove,
}: {
    count: number;
    sent: number;
    /** Above this share the number turns amber; used for bounces. */
    warnAbove?: number;
}) {
    if (sent === 0) {
        return <span className="text-muted-foreground/60">—</span>;
    }

    const share = count / sent;
    const warn = warnAbove !== undefined && share > warnAbove;

    return (
        <span className={cn(warn && 'text-amber-600 dark:text-amber-400')}>
            {count}
            <span className="text-muted-foreground">
                {' '}
                · {Math.round(share * 100)}%
            </span>
        </span>
    );
}

export function MailboxesTable({
    mailboxes,
    counts,
    selectedId,
    onSelect,
}: {
    mailboxes: Mailbox[];
    counts: Record<number, MailboxCounts>;
    selectedId: number | null;
    onSelect: (mailbox: Mailbox) => void;
}) {
    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="w-full min-w-[1100px] table-fixed border-separate border-spacing-0">
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.title}
                                    scope="col"
                                    className={cn(
                                        'sticky top-0 z-10 h-11 border-r border-b border-border bg-background px-4 text-left text-sm font-normal text-muted-foreground last:border-r-0',
                                        column.className,
                                    )}
                                >
                                    <span className="flex items-center gap-2 whitespace-nowrap">
                                        <column.icon className="size-4 shrink-0" />
                                        {column.title}
                                    </span>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody data-keeps-panel>
                        {mailboxes.map((mailbox) => {
                            const count = counts[mailbox.id];
                            const share = Math.min(
                                mailbox.sent_today / mailbox.daily_limit,
                                1,
                            );

                            return (
                                <tr
                                    key={mailbox.id}
                                    onClick={() => onSelect(mailbox)}
                                    aria-selected={mailbox.id === selectedId}
                                    className="cursor-pointer transition-colors hover:bg-accent/60 aria-selected:bg-accent"
                                >
                                    <Cell>
                                        <span className="flex items-center gap-2.5">
                                            <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-linear-to-br from-sky-400 to-violet-500 text-white">
                                                <Mail className="size-3.5" />
                                            </span>
                                            <span className="truncate font-medium">
                                                {mailbox.address}
                                            </span>
                                        </span>
                                    </Cell>
                                    <Cell className="text-muted-foreground">
                                        {mailboxTypes[mailbox.type]}
                                    </Cell>
                                    <Cell>
                                        <MailboxStatusBadge status={mailbox.status} />
                                    </Cell>
                                    <Cell>
                                        {/* Sent against the daily limit; the sender picks a box with room left. */}
                                        <span className="flex items-center gap-3">
                                            <span className="h-1.5 w-24 shrink-0 overflow-hidden rounded-full bg-border">
                                                <span
                                                    className={cn(
                                                        'block h-full rounded-full',
                                                        share >= 1
                                                            ? 'bg-red-500'
                                                            : share >= 0.75
                                                              ? 'bg-amber-500'
                                                              : 'lava',
                                                    )}
                                                    style={{ width: `${share * 100}%` }}
                                                />
                                            </span>
                                            <span className="tabular-nums">
                                                {mailbox.sent_today}
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    / {mailbox.daily_limit}
                                                </span>
                                            </span>
                                        </span>
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {count.sent}
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        <Rate count={count.replied} sent={count.sent} />
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        <Rate
                                            count={count.bounced}
                                            sent={count.sent}
                                            warnAbove={0.05}
                                        />
                                    </Cell>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="flex h-14 shrink-0 items-center gap-6 border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground tabular-nums">
                <span>Total: {mailboxes.length} mailboxes</span>
                <span>
                    {mailboxes.reduce((sum, mailbox) => sum + mailbox.sent_today, 0)}{' '}
                    of{' '}
                    {mailboxes
                        .filter((mailbox) => mailbox.status !== 'paused')
                        .reduce((sum, mailbox) => sum + mailbox.daily_limit, 0)}{' '}
                    sent today
                </span>
            </div>
        </div>
    );
}
