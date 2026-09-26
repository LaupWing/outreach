import { cn } from '@/lib/utils';

/** Grade a response rate the way the reference labels its numbers. */
export function gradeRate(rate: number | null): {
    label: string;
    className: string;
} | null {
    if (rate === null) {
        return null;
    }

    if (rate >= 15) {
        return {
            label: 'Very good',
            className:
                'border-green-500/20 bg-green-500/10 text-green-700 dark:text-green-400',
        };
    }

    if (rate >= 8) {
        return {
            label: 'Good',
            className:
                'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-400',
        };
    }

    if (rate >= 3) {
        return {
            label: 'Average',
            className:
                'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
        };
    }

    return {
        label: 'Low',
        className:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
    };
}

/** The big-number cards from the reference: title, value with a grade, one line of what it means. */
export function StatCard({
    title,
    value,
    grade,
    description,
    tone = 'plain',
}: {
    title: string;
    value: string;
    grade?: { label: string; className: string } | null;
    description: string;
    tone?: 'plain' | 'good' | 'lava';
}) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow)">
            <div className="text-sm">{title}</div>
            <div className="flex items-center gap-2">
                <span
                    className={cn(
                        'text-3xl font-semibold tracking-tight tabular-nums',
                        tone === 'good' && 'text-green-600 dark:text-green-400',
                        tone === 'lava' && 'lava bg-clip-text text-transparent',
                    )}
                >
                    {value}
                </span>
                {grade && (
                    <span
                        className={cn(
                            'rounded-md border px-1.5 py-0.5 text-[11px] font-medium',
                            grade.className,
                        )}
                    >
                        {grade.label}
                    </span>
                )}
            </div>
            <p className="text-xs text-muted-foreground">{description}</p>
        </div>
    );
}
