import { cn } from '@/lib/utils';
import type { MailboxStatus } from '@/types';

export const mailboxStatuses: Record<
    MailboxStatus,
    { label: string; className: string }
> = {
    active: {
        label: 'Active',
        className:
            'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400',
    },
    warming_up: {
        label: 'Warming up',
        className:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    paused: {
        label: 'Paused',
        className: 'border-border bg-transparent text-muted-foreground',
    },
};

export function MailboxStatusBadge({
    status,
    className,
}: {
    status: MailboxStatus;
    className?: string;
}) {
    const { label, className: tone } = mailboxStatuses[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md border px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                tone,
                className,
            )}
        >
            {status === 'warming_up' && (
                <span className="size-1.5 animate-pulse rounded-full bg-current" />
            )}
            {label}
        </span>
    );
}
