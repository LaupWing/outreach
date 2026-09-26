import {
    HelpCircle,
    Mail,
    MessageSquare,
    Percent,
    Tag,
    Target,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { NicheStatusBadge } from '@/components/niche-status-badge';
import { cn } from '@/lib/utils';
import type { Niche } from '@/types';

const columns: { title: string; icon: typeof Mail; className: string }[] = [
    { title: 'Niche', icon: Target, className: 'w-52' },
    { title: 'Status', icon: Tag, className: 'w-28' },
    { title: 'Leads', icon: Users, className: 'w-24' },
    { title: 'Emailed', icon: Mail, className: 'w-28' },
    { title: 'Replied', icon: MessageSquare, className: 'w-28' },
    { title: 'Response rate', icon: Percent, className: 'w-36' },
    { title: 'Offers', icon: Tag, className: 'w-24' },
    { title: 'Why', icon: HelpCircle, className: 'w-96' },
];

/** The counts a niche row shows, derived from leads, messages and offers. */
export type NicheCounts = {
    leads: number;
    emailed: number;
    replied: number;
    offers: number;
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

export function NichesTable({
    niches,
    counts,
    selectedId,
    onSelect,
}: {
    niches: Niche[];
    counts: Record<number, NicheCounts>;
    selectedId: number | null;
    onSelect: (niche: Niche) => void;
}) {
    const total = (key: keyof NicheCounts) =>
        niches.reduce((sum, niche) => sum + (counts[niche.id]?.[key] ?? 0), 0);

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
                        {niches.map((niche) => {
                            const count = counts[niche.id] ?? {
                                leads: 0,
                                emailed: 0,
                                replied: 0,
                                offers: 0,
                            };

                            return (
                                <tr
                                    key={niche.id}
                                    onClick={() => onSelect(niche)}
                                    aria-selected={niche.id === selectedId}
                                    className="cursor-pointer transition-colors hover:bg-accent/60 aria-selected:bg-accent"
                                >
                                    <Cell className="font-medium">
                                        <span className="flex items-center gap-2.5">
                                            <CompanyAvatar name={niche.name} />
                                            <span className="truncate">
                                                {niche.name}
                                            </span>
                                        </span>
                                    </Cell>
                                    <Cell>
                                        <NicheStatusBadge status={niche.status} />
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
                                            count.emailed === 0 &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {count.emailed > 0
                                            ? `${Math.round((count.replied / count.emailed) * 100)}%`
                                            : '—'}
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {count.offers}
                                    </Cell>
                                    <Cell className="text-muted-foreground">
                                        {niche.why ?? '—'}
                                    </Cell>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="flex h-14 shrink-0 items-center gap-6 border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground tabular-nums">
                <span>Total: {niches.length} niches</span>
                <span>{total('leads')} leads</span>
                <span>{total('replied')} replied</span>
            </div>
        </div>
    );
}
