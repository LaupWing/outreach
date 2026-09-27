import { cn } from '@/lib/utils';
import type { LeadStatus } from '@/types';

/** Label and colour per status; the order here is the pipeline order. */
export const leadStatuses: Record<
    LeadStatus,
    { label: string; className: string }
> = {
    new: {
        label: 'New',
        className: 'border-border bg-accent text-foreground/80',
    },
    emailed: {
        label: 'Emailed',
        className:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    },
    followed_up: {
        label: 'Followed up',
        className:
            'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-400',
    },
    replied: {
        label: 'Replied',
        className:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    customer: {
        label: 'Customer',
        className:
            'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400',
    },
    no: {
        label: 'No',
        className: 'border-border bg-transparent text-muted-foreground',
    },
    undeliverable: {
        label: 'Undeliverable',
        className:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
    },
};

export function LeadStatusBadge({
    status,
    className,
}: {
    status: LeadStatus;
    className?: string;
}) {
    const { label, className: tone } = leadStatuses[status];

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
