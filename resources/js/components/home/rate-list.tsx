import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type RateRow = {
    id: number;
    name: string;
    emailed: number;
    replied: number;
    /** Extra number on the right, like bounces for a mailbox. */
    note?: ReactNode;
    /** Where this row opens, like the offer's panel. */
    href?: NonNullable<InertiaLinkProps['href']>;
};

/**
 * Response per offer, niche or mailbox: a bar per row, the best on top. This is the
 * "measure" part of the briefing, so it gets the room the reference gives its cards.
 */
export function RateList({
    title,
    icon: Icon,
    rows,
    href,
}: {
    title: string;
    icon: typeof ArrowUpRight;
    rows: RateRow[];
    href: NonNullable<InertiaLinkProps['href']>;
}) {
    const rated = rows
        .map((row) => ({
            ...row,
            rate: row.emailed > 0 ? row.replied / row.emailed : null,
        }))
        .sort((a, b) => (b.rate ?? -1) - (a.rate ?? -1));

    return (
        <section className="flex flex-col rounded-lg border border-(--raised-border) bg-accent/40 shadow-(--raised-shadow)">
            <header className="flex h-11 items-center gap-2 border-b border-border px-4 text-sm">
                <Icon className="size-4 shrink-0 text-muted-foreground" />
                {title}
                <Link
                    href={href}
                    prefetch
                    className="ml-auto flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    All
                    <ArrowUpRight className="size-3.5" />
                </Link>
            </header>

            {rated.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground/60">
                    Nothing yet.
                </p>
            ) : (
                <ul className="flex flex-col p-2">
                    {rated.map((row) => {
                        const percent =
                            row.rate === null ? 0 : Math.round(row.rate * 100);
                        const inner = (
                            <>
                                <span className="flex items-center gap-3">
                                    <span className="min-w-0 flex-1 truncate">
                                        {row.name}
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                        {row.replied} / {row.emailed}
                                    </span>
                                    {row.note}
                                    <span
                                        className={cn(
                                            'w-12 shrink-0 text-right font-medium tabular-nums',
                                            row.rate === null
                                                ? 'text-muted-foreground/60'
                                                : percent >= 15
                                                  ? 'text-green-600 dark:text-green-400'
                                                  : '',
                                        )}
                                    >
                                        {row.rate === null
                                            ? '—'
                                            : `${percent}%`}
                                    </span>
                                </span>
                                <span className="mt-1.5 block h-1 overflow-hidden rounded-full bg-border">
                                    <span
                                        className={cn(
                                            'block h-full rounded-full',
                                            percent >= 15
                                                ? 'bg-green-500'
                                                : 'lava',
                                        )}
                                        style={{ width: `${percent}%` }}
                                    />
                                </span>
                            </>
                        );

                        return (
                            <li key={row.id}>
                                {row.href ? (
                                    <Link
                                        href={row.href}
                                        prefetch
                                        className="block rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent"
                                    >
                                        {inner}
                                    </Link>
                                ) : (
                                    <div className="px-2 py-2 text-sm">
                                        {inner}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
