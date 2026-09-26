import { cn } from '@/lib/utils';
import type { OfferStatus } from '@/types';

export const offerStatuses: Record<
    OfferStatus,
    { label: string; className: string }
> = {
    active: {
        label: 'Active',
        className:
            'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400',
    },
    idea: {
        label: 'Idea',
        className: 'border-border bg-accent text-foreground/80',
    },
    stopped: {
        label: 'Stopped',
        className: 'border-border bg-transparent text-muted-foreground',
    },
};

export function OfferStatusBadge({
    status,
    className,
}: {
    status: OfferStatus;
    className?: string;
}) {
    const { label, className: tone } = offerStatuses[status];

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
