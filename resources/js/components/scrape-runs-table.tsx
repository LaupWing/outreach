import {
    Calendar,
    Coins,
    Mail,
    MapPin,
    Search,
    ShieldAlert,
    Tag,
    Target,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { Niche, ScrapeRun, ScrapeRunStatus } from '@/types';

const columns: { title: string; icon: typeof Mail; className: string }[] = [
    { title: 'Search', icon: Search, className: 'w-44' },
    { title: 'Place', icon: MapPin, className: 'w-44' },
    { title: 'Niche', icon: Target, className: 'w-40' },
    { title: 'Status', icon: Tag, className: 'w-32' },
    { title: 'Found', icon: Users, className: 'w-28' },
    { title: 'With email', icon: Mail, className: 'w-40' },
    { title: 'Blocked', icon: ShieldAlert, className: 'w-32' },
    { title: 'Requests', icon: Coins, className: 'w-36' },
    { title: 'Started', icon: Calendar, className: 'w-36' },
];

const statuses: Record<ScrapeRunStatus, { label: string; className: string }> =
    {
        queued: {
            label: 'Queued',
            className: 'border-border bg-accent text-foreground/80',
        },
        running: {
            label: 'Running',
            className:
                'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-400',
        },
        done: {
            label: 'Done',
            className:
                'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400',
        },
        failed: {
            label: 'Failed',
            className:
                'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
        },
    };

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

export function ScrapeRunsTable({
    runs,
    niches,
    selectedId,
    onSelect,
}: {
    runs: ScrapeRun[];
    niches: Niche[];
    selectedId: number | null;
    onSelect: (run: ScrapeRun) => void;
}) {
    const nicheName = (id: number) =>
        niches.find((niche) => niche.id === id)?.name ?? '—';

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="w-full min-w-[1350px] table-fixed border-separate border-spacing-0">
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
                        {runs.map((run) => {
                            const status = statuses[run.status];

                            return (
                                <tr
                                    key={run.id}
                                    onClick={() => onSelect(run)}
                                    aria-selected={run.id === selectedId}
                                    className="cursor-pointer transition-colors hover:bg-accent/60 aria-selected:bg-accent"
                                >
                                    <Cell className="font-medium">
                                        {run.query}
                                    </Cell>
                                    <Cell className="text-muted-foreground">
                                        {run.place}
                                    </Cell>
                                    <Cell className="text-muted-foreground">
                                        {nicheName(run.niche_id)}
                                    </Cell>
                                    <Cell>
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-md border px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                                                status.className,
                                            )}
                                        >
                                            {run.status === 'running' && (
                                                <span className="size-1.5 animate-pulse rounded-full bg-current" />
                                            )}
                                            {status.label}
                                        </span>
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {run.found}
                                    </Cell>
                                    <Cell className="tabular-nums">
                                        {run.with_email}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            ·{' '}
                                            {run.found > 0
                                                ? Math.round(
                                                      (run.with_email /
                                                          run.found) *
                                                          100,
                                                  )
                                                : 0}
                                            %
                                        </span>
                                    </Cell>
                                    <Cell
                                        className={cn(
                                            'tabular-nums',
                                            run.blocked > 0
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {run.blocked}
                                    </Cell>
                                    <Cell className="text-muted-foreground tabular-nums">
                                        {run.requests}
                                    </Cell>
                                    <Cell className="text-muted-foreground tabular-nums">
                                        {dateTime.format(new Date(run.started_at))}
                                    </Cell>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="flex h-14 shrink-0 items-center gap-6 border-t border-border bg-accent/40 px-4 text-sm text-muted-foreground tabular-nums">
                <span>Total: {runs.length} runs</span>
                <span>
                    {runs.reduce((sum, run) => sum + run.found, 0)} businesses
                    found
                </span>
                <span>
                    {runs.reduce((sum, run) => sum + run.with_email, 0)} with
                    email
                </span>
            </div>
        </div>
    );
}
