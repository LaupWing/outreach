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
import type { Niche, PlacesUsage } from '@/types';

/** Places returns twenty results per page and at most three pages per search. */
const RESULTS_PER_PAGE = 20;
const MAX_PAGES = 3;

/** Sentinel value in the niche select that swaps it for a text field. */
const NEW_NICHE = '__new';

/**
 * Starts a Google Places search. Self-contained with its own trigger, so it can sit
 * in a page's static topbar actions. Nothing is sent yet; that comes with the scraper.
 */
export function NewScrapeDialog({
    niches,
    usage,
}: {
    niches: Niche[];
    usage: PlacesUsage;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [place, setPlace] = useState('');
    const [nicheId, setNicheId] = useState('');
    const [newNiche, setNewNiche] = useState('');
    const [pages, setPages] = useState(MAX_PAGES);

    const left = Math.max(usage.free_limit - usage.used, 0);
    const overBudget = pages > left;
    const hasNiche =
        nicheId === NEW_NICHE ? newNiche.trim() !== '' : nicheId !== '';
    const ready = query.trim() !== '' && place.trim() !== '' && hasNiche;

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
                    New scrape
                </Button>
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
                        >
                            <Input
                                id="scrape-query"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="tandarts"
                                autoFocus
                            />
                        </Field>

                        <Field
                            label="Place"
                            icon={MapPin}
                            htmlFor="scrape-place"
                        >
                            <Input
                                id="scrape-place"
                                value={place}
                                onChange={(event) =>
                                    setPlace(event.target.value)
                                }
                                placeholder="Haarlem"
                            />
                        </Field>

                        <Field
                            label="Niche"
                            icon={Target}
                            htmlFor="scrape-niche"
                        >
                            {nicheId === NEW_NICHE ? (
                                // Typing a new niche right here beats leaving the dialog to make one first.
                                <div className="flex gap-1.5">
                                    <Input
                                        id="scrape-niche"
                                        value={newNiche}
                                        onChange={(event) =>
                                            setNewNiche(event.target.value)
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
                                            setNicheId('');
                                            setNewNiche('');
                                        }}
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ) : (
                                <Select
                                    value={nicheId}
                                    onValueChange={setNicheId}
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
                                        aria-checked={pages === count}
                                        onClick={() => setPages(count)}
                                        className={cn(
                                            'flex flex-1 flex-col items-center rounded-[5px] border px-2 py-1.5 text-sm transition-colors tabular-nums',
                                            pages === count
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
                    <div className="flex items-center justify-between rounded-lg border border-(--raised-border) bg-accent/40 px-4 py-3 text-sm shadow-(--raised-shadow)">
                        <div className="flex flex-col gap-0.5">
                            <span>
                                {pages} {pages === 1 ? 'request' : 'requests'},
                                up to {pages * RESULTS_PER_PAGE} businesses
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {overBudget
                                    ? 'Not enough free requests left this month.'
                                    : `${left - pages} of ${usage.free_limit} free requests left after this run.`}
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
                            {usage.used + pages}
                            <span className="text-xs font-normal text-muted-foreground">
                                {' '}
                                / {usage.free_limit}
                            </span>
                        </span>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={!ready || overBudget}>
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
    children,
}: {
    label: string;
    icon: typeof Search;
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
