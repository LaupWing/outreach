import {
    FileText,
    ListOrdered,
    Mail,
    MessageSquare,
    Percent,
    Tag,
    Target,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { OfferStatusBadge } from '@/components/offer-status-badge';
import { cn } from '@/lib/utils';
import type { Niche, Offer } from '@/types';

const columns: { title: string; icon: typeof Mail; className: string }[] = [
    { title: 'Offer', icon: Tag, className: 'w-64' },
    { title: 'Niche', icon: Target, className: 'w-40' },
    { title: 'Status', icon: Tag, className: 'w-28' },
    { title: 'Steps', icon: ListOrdered, className: 'w-24' },
    { title: 'Leads', icon: Users, className: 'w-24' },
    { title: 'Emailed', icon: Mail, className: 'w-28' },
    { title: 'Replied', icon: MessageSquare, className: 'w-28' },
    { title: 'Response rate', icon: Percent, className: 'w-36' },
    { title: 'Description', icon: FileText, className: 'w-96' },
];

/** The counts an offer row shows, derived from steps, leads and messages. */
export type OfferCounts = {
    steps: number;
    leads: number;
    emailed: number;
    replied: number;
};

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

export function OffersTable({
    offers,
    niches,
    counts,
    selectedId,
    onSelect,
}: {
    offers: Offer[];
    niches: Niche[];
    counts: Record<number, OfferCounts>;
    selectedId: number | null;
    onSelect: (offer: Offer) => void;
}) {
    const nicheName = (id: number) =>
        niches.find((niche) => niche.id === id)?.name ?? '—';

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="w-full min-w-[1500px] table-fixed border-separate border-spacing-0">
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
                        {offers.map((offer) => {
                            const count = counts[offer.id];
                            const rate =
                                count.emailed > 0
                                    ? Math.round((count.replied / count.emailed) * 100)
                                    : null;

                            return (
                                <tr
                                    key={offer.id}
                                    onClick={() => onSelect(offer)}
                                    aria-selected={offer.id === selectedId}
                                    className="cursor-pointer transition-colors hover:bg-accent/60 aria-selected:bg-accent"
                                >
                                    <Cell>
                                        <span className="flex items-center gap-2.5">
                                            <CompanyAvatar name={offer.name} />
                                            <span className="truncate font-medium">
                                                {offer.name}
                                            </span>
                                        </span>
                                    </Cell>
                                    <Cell className="text-muted-foreground">
                                        {nicheName(offer.niche_id)}
                                    </Cell>
                                    <Cell>
                                        <OfferStatusBadge status={offer.status} />
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {count.steps > 0 ? (
                                            count.steps
                                        ) : (
                                            <span className="text-muted-foreground/60">
                                                None
                                            </span>
                                        )}
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {count.leads}
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {count.emailed}
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {count.replied}
                                    </Cell>
                                    <Cell
                                        className={cn(
                                            'tabular-nums',
                                            rate === null && 'text-muted-foreground/60',
                                            rate !== null &&
                                                rate >= 10 &&
                                                'text-green-700 dark:text-green-400',
                                        )}
                                    >
                                        {rate === null ? '—' : `${rate}%`}
                                    </Cell>
                                    <Cell className="text-muted-foreground">
                                        {offer.description ?? (
                                            <span className="text-muted-foreground/60">
                                                —
                                            </span>
                                        )}
                                    </Cell>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="flex h-14 shrink-0 items-center gap-6 border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground tabular-nums">
                <span>Total: {offers.length} offers</span>
                <span>
                    {offers.filter((offer) => offer.status === 'active').length}{' '}
                    active
                </span>
            </div>
        </div>
    );
}
