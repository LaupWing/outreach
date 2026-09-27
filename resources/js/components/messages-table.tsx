import { InfiniteScroll } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    ListOrdered,
    Mail,
    Reply,
    Send,
    Tag,
} from 'lucide-react';
import { useRef, type ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { MessageStatusBadge } from '@/components/message-status-badge';
import { cn } from '@/lib/utils';
import type { Lead, Mailbox, Message } from '@/types';

const columns: { title: string; icon: typeof Mail; className?: string }[] = [
    { title: 'Lead', icon: Building2, className: 'w-64' },
    { title: 'Step', icon: ListOrdered, className: 'w-20' },
    { title: 'Subject', icon: Mail, className: 'w-80' },
    { title: 'Status', icon: Tag, className: 'w-28' },
    { title: 'Sent from', icon: Send, className: 'w-52' },
    { title: 'Sent', icon: Calendar, className: 'w-36' },
    { title: 'Reply', icon: Reply },
];

const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

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

/** A message as the page gets it: the lead rides along, lean, for the row and the panel. */
export type MessageWithLead = Message & {
    lead: Pick<Lead, 'id' | 'company' | 'email'> | null;
};

/** What the footer says about the whole filtered set, not just the loaded rows. */
export type MessageCounts = { total: number; replied: number; bounced: number };

export function MessagesTable({
    messages,
    counts,
    mailboxes,
    selectedId,
    onSelect,
}: {
    messages: MessageWithLead[];
    counts: MessageCounts;
    mailboxes: Pick<Mailbox, 'id' | 'address'>[];
    selectedId: number | null;
    onSelect: (message: MessageWithLead) => void;
}) {
    const tableBody = useRef<HTMLTableSectionElement>(null);
    const companyOf = (message: MessageWithLead) =>
        message.lead?.company ?? 'Unknown lead';
    const mailboxOf = (id: number) =>
        mailboxes.find((mailbox) => mailbox.id === id)?.address ?? '—';

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            {/* The InfiniteScroll wrapper sits inside the scroll box, so the header keeps sticking to it. */}
            <InfiniteScroll
                data="messages"
                itemsElement={tableBody}
                preserveUrl
                onlyNext
                className="min-h-0 flex-1 overflow-auto"
                loading={() => (
                    <div className="flex h-11 items-center px-4 text-sm text-muted-foreground">
                        Loading more…
                    </div>
                )}
            >
                <table className="w-full min-w-[1000px] table-fixed border-separate border-spacing-0">
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
                    <tbody ref={tableBody} data-keeps-panel>
                        {messages.map((message) => (
                            <tr
                                key={message.id}
                                onClick={() => onSelect(message)}
                                aria-selected={message.id === selectedId}
                                className="cursor-pointer transition-colors hover:bg-accent/60 aria-selected:bg-accent"
                            >
                                <Cell>
                                    <span className="flex items-center gap-2.5">
                                        <CompanyAvatar
                                            name={companyOf(message)}
                                        />
                                        <span className="truncate font-medium">
                                            {companyOf(message)}
                                        </span>
                                    </span>
                                </Cell>
                                <Cell className="text-muted-foreground tabular-nums">
                                    {message.is_reply ? (
                                        <span className="inline-flex items-center rounded-md border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">
                                            Reply
                                        </span>
                                    ) : (
                                        message.step
                                    )}
                                </Cell>
                                <Cell>{message.subject}</Cell>
                                <Cell>
                                    <MessageStatusBadge
                                        status={message.status}
                                    />
                                </Cell>
                                <Cell className="text-muted-foreground">
                                    {mailboxOf(message.mailbox_id)}
                                </Cell>
                                <Cell className="text-muted-foreground tabular-nums">
                                    {message.sent_at ? (
                                        dateTime.format(
                                            new Date(message.sent_at),
                                        )
                                    ) : message.send_after ? (
                                        <span className="text-violet-600 dark:text-violet-400">
                                            Sends{' '}
                                            {dateTime.format(
                                                new Date(message.send_after),
                                            )}
                                        </span>
                                    ) : (
                                        '—'
                                    )}
                                </Cell>
                                <Cell className="tabular-nums">
                                    {message.reply ? (
                                        <span className="text-amber-700 dark:text-amber-400">
                                            {dateTime.format(
                                                new Date(
                                                    message.reply.received_at,
                                                ),
                                            )}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground/60">
                                            —
                                        </span>
                                    )}
                                </Cell>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </InfiniteScroll>

            <div className="flex h-14 shrink-0 items-center gap-6 border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground tabular-nums">
                <span>
                    Showing {messages.length} of {counts.total}
                </span>
                <span>{counts.replied} replies</span>
                <span>{counts.bounced} bounced</span>
            </div>
        </div>
    );
}
