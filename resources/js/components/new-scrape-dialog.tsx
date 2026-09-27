import { useForm } from '@inertiajs/react';
import { Coins, MapPin, Plus, Search, Target, X } from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { store } from '@/routes/scrape';
import type { Niche, PlacesUsage } from '@/types';

/** Places returns twenty results per page and at most three pages per search. */
const RESULTS_PER_PAGE = 20;
const MAX_PAGES = 3;

/** Sentinel value in the niche select that swaps it for a text field. */
const NEW_NICHE = '__new';

/**
 * Starts a Google Places search. Self-contained with its own trigger, so it can sit
 * in a page's static topbar actions. Posting queues the run; the scraper picks it up later.
 */
export function NewScrapeDialog({
    niches,
    usage,
    prefill,
    trigger,
}: {
    niches: Niche[];
    usage: PlacesUsage;
    /** "Run again" starts from an earlier run's search. */
    prefill?: { query: string; place: string; niche_id: number };
    trigger?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        query: prefill?.query ?? '',
        place: prefill?.place ?? '',
        /** A niche id as string, or the sentinel while a new one is being typed. */
        niche_id: prefill ? String(prefill.niche_id) : '',
        new_niche: '',
        pages: MAX_PAGES,
    });
    const { data, setData, errors, processing } = form;

    const left = Math.max(usage.free_limit - usage.used, 0);
    const overBudget = data.pages > left;
    const creatingNiche = data.niche_id === NEW_NICHE;
    const hasNiche = creatingNiche
        ? data.new_niche.trim() !== ''
        : data.niche_id !== '';
    const ready =
        data.query.trim() !== '' && data.place.trim() !== '' && hasNiche;

    // The server wants one of the two: an existing id or the name of a new niche.
    form.transform((values) => ({
        query: values.query,
        place: values.place,
        pages: values.pages,
        ...(values.niche_id === NEW_NICHE
            ? { new_niche: values.new_niche.trim() }
            : { niche_id: Number(values.niche_id) }),
    }));

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    const toggle = (value: boolean) => {
        setOpen(value);

        if (value) {
            form.clearErrors();
        }
    };

    return (
        <Dialog open={open} onOpenChange={toggle}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground"
                    >
                        <Plus />
                        New scrape
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>New scrape</DialogTitle>
                        <DialogDescription>
                            Searches Google Places for businesses and their
                            websites, then reads each site for an email address
                            and signals.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <Field
                            label="Search"
                            icon={Search}
                            htmlFor="scrape-query"
                            error={errors.query}
                        >
                            <Input
                                id="scrape-query"
                                value={data.query}
                                onChange={(event) =>
                                    setData('query', event.target.value)
                                }
                                placeholder="tandarts"
                                autoFocus
                            />
                        </Field>

                        <Field
                            label="Place"
                            icon={MapPin}
                            htmlFor="scrape-place"
                            error={errors.place}
                        >
                            <Input
                                id="scrape-place"
                                value={data.place}
                                onChange={(event) =>
                                    setData('place', event.target.value)
                                }
                                placeholder="Haarlem"
                            />
                        </Field>

                        <Field
                            label="Niche"
                            icon={Target}
                            htmlFor="scrape-niche"
                            error={errors.niche_id ?? errors.new_niche}
                        >
                            {creatingNiche ? (
                                // Typing a new niche right here beats leaving the dialog to make one first.
                                <div className="flex gap-1.5">
                                    <Input
                                        id="scrape-niche"
                                        value={data.new_niche}
                                        onChange={(event) =>
                                            setData(
                                                'new_niche',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Name of the new niche"
                                        autoFocus
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="shrink-0 text-muted-foreground"
                                        aria-label="Pick an existing niche instead"
                                        onClick={() => {
                                            setData('niche_id', '');
                                            setData('new_niche', '');
                                        }}
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ) : (
                                <Select
                                    value={data.niche_id}
                                    onValueChange={(value) =>
                                        setData('niche_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="scrape-niche"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pick a niche" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {niches.map((niche) => (
                                            <SelectItem
                                                key={niche.id}
                                                value={String(niche.id)}
                                            >
                                                {niche.name}
                                            </SelectItem>
                                        ))}
                                        <SelectSeparator />
                                        <SelectItem value={NEW_NICHE}>
                                            <Plus className="size-4" />
                                            New niche…
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>

                        <Field label="Pages" icon={Coins}>
                            {/* Segmented control: a sunken track, the chosen page raised like the active sidebar item. */}
                            <div
                                role="radiogroup"
                                aria-label="Pages"
                                className="flex rounded-md border border-border bg-accent/40 p-0.5"
                            >
                                {Array.from(
                                    { length: MAX_PAGES },
                                    (_, index) => index + 1,
                                ).map((count) => (
                                    <button
                                        key={count}
                                        type="button"
                                        role="radio"
                                        aria-checked={data.pages === count}
                                        onClick={() => setData('pages', count)}
                                        className={cn(
                                            'flex flex-1 flex-col items-center rounded-[5px] border px-2 py-1.5 text-sm tabular-nums transition-colors',
                                            data.pages === count
                                                ? 'border-(--raised-border) bg-background text-foreground shadow-(--raised-shadow)'
                                                : 'border-transparent text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {count}
                                        <span className="text-[10px] font-normal text-muted-foreground">
                                            {count * RESULTS_PER_PAGE}{' '}
                                            businesses
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </Field>
                    </div>

                    {/* What this run costs against the free tier, before you press start. */}
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between rounded-lg border border-(--raised-border) bg-accent/40 px-4 py-3 text-sm shadow-(--raised-shadow)">
                            <div className="flex flex-col gap-0.5">
                                <span>
                                    {data.pages}{' '}
                                    {data.pages === 1 ? 'request' : 'requests'},
                                    up to {data.pages * RESULTS_PER_PAGE}{' '}
                                    businesses
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {overBudget
                                        ? 'Not enough free requests left this month.'
                                        : `${left - data.pages} of ${usage.free_limit} free requests left after this run.`}
                                </span>
                            </div>
                            <span
                                className={cn(
                                    'text-lg font-semibold tabular-nums',
                                    overBudget
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'lava bg-clip-text text-transparent',
                                )}
                            >
                                {usage.used + data.pages}
                                <span className="text-xs font-normal text-muted-foreground">
                                    {' '}
                                    / {usage.free_limit}
                                </span>
                            </span>
                        </div>
                        {/* The server checks the budget too; the page's usage can lag a run queued elsewhere. */}
                        {errors.pages && (
                            <p className="text-xs text-red-600 dark:text-red-400">
                                {errors.pages}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={!ready || overBudget || processing}
                        >
                            <Search />
                            Start scrape
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
    error,
    children,
}: {
    label: string;
    icon: typeof Search;
    htmlFor?: string;
    error?: string;
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
            {error && (
                <p className="text-xs text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
        </div>
    );
}
