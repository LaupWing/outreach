import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Step indicator for dialogs that ask too much for one screen. Done steps get a
 * tick, the current one is raised like the active sidebar item, later ones are dim.
 */
export function DialogSteps({
    steps,
    current,
    onSelect,
}: {
    steps: string[];
    /** Zero-based. */
    current: number;
    /** Lets you jump back to a step you already passed. */
    onSelect?: (index: number) => void;
}) {
    return (
        <ol className="flex gap-1 rounded-md border border-border bg-accent/40 p-0.5">
            {steps.map((label, index) => {
                const done = index < current;
                const active = index === current;

                return (
                    <li key={label} className="flex-1">
                        <button
                            type="button"
                            disabled={!done}
                            onClick={() => onSelect?.(index)}
                            className={cn(
                                'flex w-full items-center justify-center gap-1.5 rounded-[5px] border px-2 py-1.5 text-xs transition-colors tabular-nums disabled:cursor-default',
                                active
                                    ? 'border-(--raised-border) bg-background text-foreground shadow-(--raised-shadow)'
                                    : done
                                      ? 'border-transparent text-muted-foreground hover:text-foreground'
                                      : 'border-transparent text-muted-foreground/60',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-4 items-center justify-center rounded-full text-[10px]',
                                    done
                                        ? 'bg-linear-to-br from-sky-400 to-violet-500 text-white'
                                        : active
                                          ? 'bg-foreground text-background'
                                          : 'bg-border',
                                )}
                            >
                                {done ? <Check className="size-2.5" /> : index + 1}
                            </span>
                            {label}
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}
