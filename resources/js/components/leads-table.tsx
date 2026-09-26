import {
    Building2,
    Calendar,
    Mail,
    MapPin,
    Send,
    Tag,
    Target,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadStatusBadge } from '@/components/lead-status-badge';
import { cn } from '@/lib/utils';
import type { Lead, Niche } from '@/types';

// Fixed widths in px, so a column never gets narrower than its header; the table scrolls instead.
const columns: { title: string; icon: typeof Mail; className: string }[] = [
    { title: 'Company', icon: Building2, className: 'w-64' },
    { title: 'Email', icon: Mail, className: 'w-64' },
    { title: 'City', icon: MapPin, className: 'w-36' },
    { title: 'Niche', icon: Target, className: 'w-40' },
    { title: 'Status', icon: Tag, className: 'w-36' },
    { title: 'Sent from', icon: Send, className: 'w-48' },
    { title: 'Next action', icon: Calendar, className: 'w-36' },
];

const shortDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
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

export function LeadsTable({
    leads,
    niches,
    sentFrom,
    selectedId,
    onSelect,
}: {
    leads: Lead[];
    niches: Niche[];
    /** Address of the mailbox that last mailed each lead, by lead id. */
    sentFrom: Record<number, string | undefined>;
    selectedId: number | null;
    onSelect: (lead: Lead) => void;
}) {
    const nicheName = (id: number) =>
        niches.find((niche) => niche.id === id)?.name ?? '—';

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
                        {leads.map((lead) => (
                            <tr
                                key={lead.id}
                                onClick={() => onSelect(lead)}
                                aria-selected={lead.id === selectedId}
                                className="cursor-pointer transition-colors hover:bg-accent/60 aria-selected:bg-accent"
                            >
                                <Cell>
                                    <span className="flex items-center gap-2.5">
                                        <CompanyAvatar name={lead.company} />
                                        <span className="truncate font-medium">
                                            {lead.company}
                                        </span>
                                    </span>
                                </Cell>
                                <Cell className="text-muted-foreground">
                                    {lead.email ?? (
                                        <span className="text-muted-foreground/60">
                                            No email found
                                        </span>
                                    )}
                                </Cell>
                                <Cell className="text-muted-foreground">
                                    {lead.city ?? '—'}
                                </Cell>
                                <Cell className="text-muted-foreground">
                                    {nicheName(lead.niche_id)}
                                </Cell>
                                <Cell>
                                    <LeadStatusBadge status={lead.status} />
                                </Cell>
                                <Cell className="text-muted-foreground">
                                    {sentFrom[lead.id] ?? '—'}
                                </Cell>
                                <Cell className="text-muted-foreground tabular-nums">
                                    {lead.next_action_at
                                        ? shortDate.format(
                                              new Date(lead.next_action_at),
                                          )
                                        : '—'}
                                </Cell>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Filled band, same height as the sidebar footer. */}
            <div className="flex h-14 shrink-0 items-center border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground">
                Total: {leads.length} leads
            </div>
        </div>
    );
}
