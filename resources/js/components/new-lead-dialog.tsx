import {
    Building2,
    ChevronLeft,
    ChevronRight,
    Flag,
    Globe,
    Mail,
    MapPin,
    Phone,
    Plus,
    Sparkles,
    Tag,
    Target,
    X,
} from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';
import { DialogSteps } from '@/components/dialog-steps';
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
import type { LeadStatus, Niche, Offer } from '@/types';

/** Sentinel value in the niche select that swaps it for a text field. */
const NEW_NICHE = '__new';

/** Who they are first, then how to reach them; nine fields is too many for one screen. */
const STEPS = ['Business', 'Contact'];

/** A hand-added lead is either untouched or already mailed from elsewhere; the rest follows from sending. */
const STATUSES: { value: LeadStatus; label: string }[] = [
    { value: 'new', label: 'New' },
    { value: 'emailed', label: 'Emailed' },
];

/**
 * Adds a lead the scraper did not find. Self-contained with its own trigger, so it
 * can sit in a page's static topbar actions. Nothing is saved yet; that comes with Laravel.
 */
export function NewLeadDialog({
    niches,
    offers,
}: {
    niches: Niche[];
    offers: Offer[];
}) {
    const [open, setOpen] = useState(false);
    const [step, setStep] = useState(0);
    const [company, setCompany] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [website, setWebsite] = useState('');
    const [city, setCity] = useState('');
    const [nicheId, setNicheId] = useState('');
    const [newNiche, setNewNiche] = useState('');
    const [offerId, setOfferId] = useState('');
    const [status, setStatus] = useState<LeadStatus>('new');
    const [hook, setHook] = useState('');

    const hasNiche =
        nicheId === NEW_NICHE ? newNiche.trim() !== '' : nicheId !== '';
    const ready = company.trim() !== '' && hasNiche;

    // Offers belong to a niche; a new niche has none yet.
    const nicheOffers = offers.filter(
        (offer) => String(offer.niche_id) === nicheId,
    );

    const pickNiche = (value: string) => {
        setNicheId(value);
        setOfferId('');
    };

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setOpen(false);
    };

    const toggle = (value: boolean) => {
        setOpen(value);
        setStep(0);
    };

    return (
        <Dialog open={open} onOpenChange={toggle}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground"
                >
                    <Plus />
                    Lead
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>New lead</DialogTitle>
                        <DialogDescription>
                            Add a business the scraper did not find. Fill what
                            you know; the rest can be enriched later.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogSteps
                        steps={STEPS}
                        current={step}
                        onSelect={setStep}
                    />

                    {step === 0 ? (
                        <div className="grid gap-4">
                            <Field
                                label="Company"
                                icon={Building2}
                                htmlFor="lead-company"
                        >
                                <Input
                                    id="lead-company"
                                    value={company}
                                    onChange={(event) =>
                                        setCompany(event.target.value)
                                    }
                                    placeholder="Tandartspraktijk De Molen"
                                    autoFocus
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Website"
                                    icon={Globe}
                                    htmlFor="lead-website"
                                >
                                    <Input
                                        id="lead-website"
                                        type="url"
                                        value={website}
                                        onChange={(event) =>
                                            setWebsite(event.target.value)
                                        }
                                        placeholder="https://example.nl"
                                    />
                                </Field>

                                <Field
                                    label="City"
                                    icon={MapPin}
                                    htmlFor="lead-city"
                                >
                                    <Input
                                        id="lead-city"
                                        value={city}
                                        onChange={(event) =>
                                            setCity(event.target.value)
                                        }
                                        placeholder="Haarlem"
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Niche"
                                icon={Target}
                                htmlFor="lead-niche"
                        >
                                {nicheId === NEW_NICHE ? (
                                    // Typing a new niche right here beats leaving the dialog to make one first.
                                    <div className="flex gap-1.5">
                                        <Input
                                            id="lead-niche"
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
                                                pickNiche('');
                                                setNewNiche('');
                                            }}
                                        >
                                            <X />
                                        </Button>
                                    </div>
                                ) : (
                                    <Select
                                        value={nicheId}
                                        onValueChange={pickNiche}
                                    >
                                        <SelectTrigger
                                            id="lead-niche"
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

                            <Field label="Offer" icon={Tag} htmlFor="lead-offer">
                                <Select
                                    value={offerId}
                                    onValueChange={setOfferId}
                                    disabled={nicheOffers.length === 0}
                                >
                                    <SelectTrigger
                                        id="lead-offer"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="No offer yet" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {nicheOffers.map((offer) => (
                                            <SelectItem
                                                key={offer.id}
                                                value={String(offer.id)}
                                            >
                                                {offer.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            </div>
                    ) : (
                        <div className="grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Email"
                                    icon={Mail}
                                    htmlFor="lead-email"
                                >
                                    <Input
                                        id="lead-email"
                                        type="email"
                                        value={email}
                                        onChange={(event) =>
                                            setEmail(event.target.value)
                                        }
                                        placeholder="info@example.nl"
                                    />
                                </Field>

                                <Field
                                    label="Phone"
                                    icon={Phone}
                                    htmlFor="lead-phone"
                                >
                                    <Input
                                        id="lead-phone"
                                        type="tel"
                                        value={phone}
                                        onChange={(event) =>
                                            setPhone(event.target.value)
                                        }
                                        placeholder="023 123 4567"
                                    />
                                </Field>
                            </div>

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

                            <Field label="Hook" icon={Sparkles} htmlFor="lead-hook">
                                {/* No Textarea component in ui/, so this mirrors the Input styles. */}
                                <textarea
                                    id="lead-hook"
                                    value={hook}
                                    onChange={(event) =>
                                        setHook(event.target.value)
                                    }
                                    rows={3}
                                    placeholder="What you noticed on their site, in one or two lines"
                                    className="flex w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                />
                            </Field>

                            {/* Source is not a choice here: everything from this dialog is manual. */}
                            <p className="text-xs text-muted-foreground">
                                    Added by hand; the enricher can still read the site for
                                    signals.
                                </p>
                            </div>
                    )}

                    <DialogFooter>
                        {step === 0 ? (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    disabled={!ready}
                                    onClick={() => setStep(1)}
                                >
                                    Next
                                    <ChevronRight />
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setStep(0)}
                                >
                                    <ChevronLeft />
                                    Back
                                </Button>
                                <Button type="submit" disabled={!ready}>
                                    <Plus />
                                    Add lead
                                </Button>
                            </>
                        )}
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
