import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * The detail panel beside a table, like the customer card in the reference. It stays
 * mounted and slides open and closed, so the table's width eases along with it.
 */
export function SidePanel({
    open,
    children,
}: {
    open: boolean;
    /** Keep passing the last content while closing, so it does not blink away. */
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'shrink-0 overflow-hidden transition-[width] duration-300 ease-out',
                open ? 'w-120' : 'w-0',
            )}
            aria-hidden={!open}
        >
            {children && (
                <aside
                    className={cn(
                        'flex h-full w-120 flex-col border-l border-border bg-background transition-transform duration-300 ease-out',
                        open ? 'translate-x-0' : 'translate-x-full',
                    )}
                >
                    {children}
                </aside>
            )}
        </div>
    );
}

/** Breadcrumb-style header with the close button, same height as the filter bar next to it. */
export function SidePanelHeader({
    parent,
    title,
    onClose,
}: {
    parent: string;
    title: string;
    onClose: () => void;
}) {
    return (
        <div className="flex h-12 shrink-0 items-center gap-2 border-b border-border px-4 text-sm">
            <span className="shrink-0 text-muted-foreground">{parent}</span>
            <span className="shrink-0 text-muted-foreground">/</span>
            <span className="truncate">{title}</span>
            <Button
                variant="ghost"
                size="icon"
                className="ml-auto size-7 shrink-0 text-muted-foreground"
                onClick={onClose}
                aria-label="Close"
            >
                <X className="size-4" />
            </Button>
        </div>
    );
}

/** Underlined tab strip used inside the panel. */
export function SidePanelTabs<T extends string>({
    tabs,
    value,
    onChange,
}: {
    tabs: { key: T; label: string; icon: typeof X; badge?: number }[];
    value: T;
    onChange: (key: T) => void;
}) {
    return (
        <div className="flex shrink-0 gap-1 border-b border-border px-3">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    onClick={() => onChange(tab.key)}
                    className={cn(
                        '-mb-px flex items-center gap-1.5 border-b-2 px-2 py-2.5 text-sm transition-colors',
                        value === tab.key
                            ? 'border-foreground text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    <tab.icon className="size-4 shrink-0" />
                    {tab.label}
                    {tab.badge !== undefined && tab.badge > 0 && (
                        <span className="rounded-full bg-accent px-1.5 text-[10px] text-muted-foreground tabular-nums">
                            {tab.badge}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}

/** One label/value line in a panel. */
export function SidePanelRow({
    icon: Icon,
    label,
    children,
}: {
    icon: typeof X;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 px-5 py-2.5 text-sm">
            <dt className="flex w-32 shrink-0 items-center gap-2 text-muted-foreground">
                <Icon className="size-4 shrink-0" />
                {label}
            </dt>
            <dd className="min-w-0 truncate">{children}</dd>
        </div>
    );
}

export function SidePanelEmpty({ children = '—' }: { children?: ReactNode }) {
    return <span className="text-muted-foreground/60">{children}</span>;
}
