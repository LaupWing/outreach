import { Moon, Sun } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

// A little overshoot, so the icons land with a bounce.
const motionClassName =
    'duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)] motion-reduce:transition-none';

// Both icons share the button. The one that leaves spins out and shrinks while the other spins in.
const iconClassName = cn(
    'absolute inset-0 m-auto size-4 transition-[rotate,scale,opacity]',
    motionClassName,
);

/**
 * Topbar icon button that flips light and dark. What shows is driven by the `dark` class on
 * <html>, not by React state, so it is right from the first paint.
 */
export function AppearanceToggle({ className }: { className?: string }) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    role="switch"
                    aria-checked={isDark}
                    aria-label="Toggle dark mode"
                    suppressHydrationWarning
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    className={cn(
                        'group/appearance relative flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground',
                        className,
                    )}
                >
                    <Sun
                        className={cn(
                            iconClassName,
                            'text-amber-500 drop-shadow-[0_0_6px_--theme(--color-amber-400/60%)] group-hover/appearance:rotate-45 dark:scale-0 dark:-rotate-180 dark:opacity-0',
                        )}
                    />
                    <Moon
                        className={cn(
                            iconClassName,
                            'scale-0 rotate-180 text-indigo-300 opacity-0 drop-shadow-[0_0_6px_--theme(--color-indigo-400/70%)] dark:scale-100 dark:rotate-0 dark:opacity-100 dark:group-hover/appearance:-rotate-12',
                        )}
                    />
                </button>
            </TooltipTrigger>
            <TooltipContent side="bottom">
                {isDark ? 'Light mode' : 'Dark mode'}
            </TooltipContent>
        </Tooltip>
    );
}
