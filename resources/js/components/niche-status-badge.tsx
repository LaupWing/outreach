import { cn } from '@/lib/utils';
import type { NicheStatus } from '@/types';

/** Label and colour per status; the order here is the path a niche takes. */
export const nicheStatuses: Record<
    NicheStatus,
    { label: string; className: string }
> = {
    idea: {
        label: 'Idea',
        className: 'border-border bg-accent text-foreground/80',
    },
    testing: {
        label: 'Testing',
        className: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    },
    proven: {
        label: 'Proven',
        className: 'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400',
    },
    dropped: {
        label: 'Dropped',
        className: 'border-border bg-transparent text-muted-foreground',
    },
};

export function NicheStatusBadge({
    status,
    className,
}: {
    status: NicheStatus;
    className?: string;
}) {
    const { label, className: tone } = nicheStatuses[status];

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md border px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                tone,
                className,
            )}
        >
            {label}
        </span>
    );
}
