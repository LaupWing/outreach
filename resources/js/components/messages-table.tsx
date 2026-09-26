import {
    Building2,
    Calendar,
    ListOrdered,
    Mail,
    Reply,
    Send,
    Tag,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { MessageStatusBadge } from '@/components/message-status-badge';
import { cn } from '@/lib/utils';
import type { Lead, Mailbox, Message } from '@/types';

const columns: { title: string; icon: typeof Mail; className: string }[] = [
    { title: 'Lead', icon: Building2, className: 'w-64' },
    { title: 'Step', icon: ListOrdered, className: 'w-20' },
    { title: 'Subject', icon: Mail, className: 'w-80' },
    { title: 'Status', icon: Tag, className: 'w-28' },
    { title: 'Sent from', icon: Send, className: 'w-52' },
    { title: 'Sent', icon: Calendar, className: 'w-36' },
    { title: 'Reply', icon: Reply, className: 'w-36' },
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

export function MessagesTable({
    messages,
    leads,
    mailboxes,
    selectedId,
    onSelect,
}: {
    messages: Message[];
    leads: Lead[];
    mailboxes: Mailbox[];
    selectedId: number | null;
    onSelect: (message: Message) => void;
}) {
    const companyOf = (id: number) =>
        leads.find((lead) => lead.id === id)?.company ?? 'Unknown lead';
    const mailboxOf = (id: number) =>
        mailboxes.find((mailbox) => mailbox.id === id)?.address ?? '—';

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="w-full min-w-[1300px] table-fixed border-separate border-spacing-0">
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
                    <tbody>
                        {messages.map((message) => (
                            <tr
                                key={message.id}
                                onClick={() => onSelect(message)}
                                aria-selected={message.id === selectedId}
                                className="cursor-pointer transition-colors hover:bg-accent/60 aria-selected:bg-accent"
                            >
                                <Cell>
                                    <span className="flex items-center gap-2.5">
                                        <CompanyAvatar name={companyOf(message.lead_id)} />
                                        <span className="truncate font-medium">
                                            {companyOf(message.lead_id)}
                                        </span>
                                    </span>
                                </Cell>
                                <Cell className="text-muted-foreground tabular-nums">
                                    {message.step}
                                </Cell>
                                <Cell>{message.subject}</Cell>
                                <Cell>
                                    <MessageStatusBadge status={message.status} />
                                </Cell>
                                <Cell className="text-muted-foreground">
                                    {mailboxOf(message.mailbox_id)}
                                </Cell>
                                <Cell className="text-muted-foreground tabular-nums">
                                    {message.sent_at
                                        ? dateTime.format(new Date(message.sent_at))
                                        : '—'}
                                </Cell>
                                <Cell className="tabular-nums">
                                    {message.reply ? (
                                        <span className="text-amber-700 dark:text-amber-400">
                                            {dateTime.format(
                                                new Date(message.reply.received_at),
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
            </div>

            <div className="flex h-14 shrink-0 items-center gap-6 border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground tabular-nums">
                <span>Total: {messages.length} messages</span>
                <span>
                    {messages.filter((message) => message.reply).length} replies
                </span>
                <span>
                    {messages.filter((message) => message.status === 'bounced').length}{' '}
                    bounced
                </span>
            </div>
        </div>
    );
}
