import { ChevronDown } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type FilterOption = { value: string; label: string };

/** Turns a selection into the short summary shown on the trigger. */
export function summarize(
    options: FilterOption[],
    selected: string[],
    placeholder = 'Any',
): string {
    if (selected.length === 0) {
        return placeholder;
    }

    const labels = selected.map(
        (value) => options.find((option) => option.value === value)?.label,
    );

    return labels.length > 2
        ? `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`
        : labels.join(', ');
}

/**
 * The raised box every filter opens from: label in grey, the selection in white,
 * a hairline border with the top highlight from the sidebar's active item.
 */
export function FilterTrigger({
    label,
    icon,
    active,
    className,
    children,
    ...props
}: ComponentProps<'button'> & {
    label: string;
    icon?: ReactNode;
    active: boolean;
}) {
    return (
        <button
            type="button"
            className={cn(
                'flex h-8 items-center gap-1.5 rounded-md border border-(--raised-border) bg-background px-2.5 text-xs shadow-(--raised-shadow) transition-colors hover:bg-accent data-[state=open]:bg-accent',
                className,
            )}
            {...props}
        >
            {icon}
            <span className="text-muted-foreground">{label}</span>
            <span
                className={cn(
                    'max-w-40 truncate',
                    active ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {children}
            </span>
            <ChevronDown className="size-3.5 text-muted-foreground" />
        </button>
    );
}
