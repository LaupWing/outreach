import { cn } from '@/lib/utils';
import type { PlacesUsage } from '@/types';

const resetDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'long',
});

/**
 * The free monthly volume of the Places SKU. One request is one page of twenty
 * results, so the bar tells you how many searches are left before Google starts billing.
 */
export function PlacesUsageCard({ usage }: { usage: PlacesUsage }) {
    const share = Math.min(usage.used / usage.free_limit, 1);
    const percent = Math.round(share * 100);
    const left = Math.max(usage.free_limit - usage.used, 0);

    // Brand gradient while there is room, amber past three quarters, red when it is nearly gone.
    const tone =
        share >= 0.9
            ? 'bg-red-500 text-red-600 dark:text-red-400'
            : share >= 0.75
              ? 'bg-amber-500 text-amber-600 dark:text-amber-400'
              : 'lava text-transparent';

    return (
        <div className="flex flex-col gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow)">
            <div className="flex items-baseline justify-between gap-4">
                <div className="flex flex-col gap-0.5">
                    <span className="text-sm">Google Places, free tier</span>
                    <span className="text-xs text-muted-foreground">
                        {usage.sku}, resets{' '}
                        {resetDate.format(new Date(usage.resets_at))}
                    </span>
                </div>
                <div className="flex items-baseline gap-1.5 tabular-nums">
                    <span
                        className={cn(
                            'bg-clip-text text-2xl font-semibold',
                            tone,
                        )}
                    >
                        {usage.used.toLocaleString('en-GB')}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        / {usage.free_limit.toLocaleString('en-GB')} requests
                    </span>
                </div>
            </div>

            <div
                role="progressbar"
                aria-valuenow={usage.used}
                aria-valuemin={0}
                aria-valuemax={usage.free_limit}
                className="h-1.5 overflow-hidden rounded-full bg-border"
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width] duration-500',
                        tone,
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>

            <div className="flex justify-between text-xs text-muted-foreground tabular-nums">
                <span>
                    {percent}% used, then ${usage.price_per_1000} per 1,000
                </span>
                <span>
                    {left.toLocaleString('en-GB')} left, about{' '}
                    {(left * 20).toLocaleString('en-GB')} businesses
                </span>
            </div>
        </div>
    );
}
