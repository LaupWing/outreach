import { Flag, HelpCircle, Plus, Target } from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';
import { nicheStatuses } from '@/components/niche-status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { NicheStatus } from '@/types';

/** Same order as the badge map: the path a niche takes. */
const STATUSES = (Object.keys(nicheStatuses) as NicheStatus[]).map(
    (value) => ({ value, label: nicheStatuses[value].label }),
);

const textareaClassName =
    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

/**
 * Creates a niche: a market to test an offer on. Self-contained with its own
 * trigger, so it can sit in a page's static topbar actions. Findings are left
 * out; they come from testing. Nothing is saved yet.
 */
export function NewNicheDialog() {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [status, setStatus] = useState<NicheStatus>('idea');
    const [why, setWhy] = useState('');

    const ready = name.trim() !== '';

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setOpen(false);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground"
                >
                    <Plus />
                    Niche
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>New niche</DialogTitle>
                        <DialogDescription>
                            A market you want to test an offer on. Start it as
                            an idea; it moves to testing once the first scrape
                            runs.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <Field label="Name" icon={Target} htmlFor="niche-name">
                            <Input
                                id="niche-name"
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                                placeholder="Tandartsen"
                                autoFocus
                            />
                        </Field>

                        <Field label="Status" icon={Flag}>
                            {/* Segmented control: a sunken track, the chosen status raised like the active sidebar item. */}
                            <div
                                role="radiogroup"
                                aria-label="Status"
                                className="flex rounded-md border border-border bg-accent/40 p-0.5"
                            >
                                {STATUSES.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={status === option.value}
                                        onClick={() => setStatus(option.value)}
                                        className={cn(
                                            'flex flex-1 items-center justify-center rounded-[5px] border px-2 py-1.5 text-sm transition-colors',
                                            status === option.value
                                                ? 'border-(--raised-border) bg-background text-foreground shadow-(--raised-shadow)'
                                                : 'border-transparent text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                        </Field>

                        <Field label="Why" icon={HelpCircle} htmlFor="niche-why">
                            <textarea
                                id="niche-why"
                                value={why}
                                onChange={(event) => setWhy(event.target.value)}
                                placeholder="Why this niche might work: money, pain, reachability."
                                className={textareaClassName}
                            />
                        </Field>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={!ready}>
                            <Plus />
                            Add niche
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    icon: Icon,
    htmlFor,
    children,
}: {
    label: string;
    icon: typeof Target;
    htmlFor?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label
                htmlFor={htmlFor}
                className="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Icon className="size-3.5 shrink-0" />
                {label}
            </Label>
            {children}
        </div>
    );
}
