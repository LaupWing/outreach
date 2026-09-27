import { cn } from '@/lib/utils';
import type { MessageStatus } from '@/types';

export const messageStatuses: Record<
    MessageStatus,
    { label: string; className: string }
> = {
    draft: {
        label: 'Draft',
        className: 'border-border bg-accent text-foreground/80',
    },
    queued: {
        label: 'Queued',
        className:
            'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-400',
    },
    sent: {
        label: 'Sent',
        className:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    },
    replied: {
        label: 'Replied',
        className:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    failed: {
        label: 'Failed',
        className:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
    },
    bounced: {
        label: 'Bounced',
        className:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
    },
};

export function MessageStatusBadge({
    status,
    className,
}: {
    status: MessageStatus;
    className?: string;
}) {
    const { label, className: tone } = messageStatuses[status];

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
