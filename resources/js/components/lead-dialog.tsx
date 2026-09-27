import { router } from '@inertiajs/react';
import {
    Building2,
    Check,
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
import InputError from '@/components/input-error';
import { leadStatuses } from '@/components/lead-status-badge';
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
import { store as storeLead, update as updateLead } from '@/routes/leads';
import type { Lead, LeadStatus, Niche, Offer } from '@/types';

/** Sentinel value in the niche select that swaps it for a text field. */
const NEW_NICHE = '__new';

/** Who they are first, then how to reach them; nine fields is too many for one screen. */
const STEPS = ['Business', 'Contact'];

/** Every status, in pipeline order; an existing lead can be moved anywhere. */
const ALL_STATUSES = (Object.keys(leadStatuses) as LeadStatus[]).map(
    (value) => ({ value, label: leadStatuses[value].label }),
);

/** A hand-added lead is either untouched or already mailed from elsewhere; the rest follows from sending. */
const NEW_STATUSES = ALL_STATUSES.filter(
    (option) => option.value === 'new' || option.value === 'emailed',
);

/** Form values for a lead, or the blanks for a new one. */
const valuesFrom = (lead?: Lead) => ({
    company: lead?.company ?? '',
    email: lead?.email ?? '',
    phone: lead?.phone ?? '',
    website: lead?.website ?? '',
    city: lead?.city ?? '',
    nicheId: lead ? String(lead.niche_id) : '',
    offerId: lead?.offer_id ? String(lead.offer_id) : '',
    status: lead?.status ?? ('new' as LeadStatus),
    hook: lead?.hook ?? '',
});

/**
 * Adds a lead the scraper did not find, or edits an existing one when `lead` is given.
 * Self-contained with its own trigger, so it can sit in a page's static topbar actions.
 * Posts to leads.store, or patches leads.update when editing.
 */
export function LeadDialog({
    lead,
    niches,
    offers,
    trigger,
}: {
    lead?: Lead;
    niches: Niche[];
    offers: Offer[];
    /** Replaces the default "+ Lead" button. */
    trigger?: ReactNode;
}) {
    const initial = valuesFrom(lead);
    const editing = lead !== undefined;
    const statuses = editing ? ALL_STATUSES : NEW_STATUSES;

    const [open, setOpen] = useState(false);
    const [step, setStep] = useState(0);
    const [company, setCompany] = useState(initial.company);
    const [email, setEmail] = useState(initial.email);
    const [phone, setPhone] = useState(initial.phone);
    const [website, setWebsite] = useState(initial.website);
    const [city, setCity] = useState(initial.city);
    const [nicheId, setNicheId] = useState(initial.nicheId);
    const [newNiche, setNewNiche] = useState('');
    const [offerId, setOfferId] = useState(initial.offerId);
    const [status, setStatus] = useState<LeadStatus>(initial.status);
    const [hook, setHook] = useState(initial.hook);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

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

        const blank = (value: string) =>
            value.trim() === '' ? null : value.trim();
        const data = {
            company: company.trim(),
            email: blank(email),
            phone: blank(phone),
            website: blank(website),
            city: blank(city),
            niche_id: nicheId === NEW_NICHE ? null : Number(nicheId),
            new_niche: nicheId === NEW_NICHE ? newNiche.trim() : null,
            offer_id: offerId === '' ? null : Number(offerId),
            status,
            hook: blank(hook),
        };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (fresh: Record<string, string>) => setErrors(fresh),
            onSuccess: () => setOpen(false),
        };

        if (lead) {
            router.patch(updateLead.url(lead.id), data, options);
        } else {
            router.post(storeLead.url(), data, options);
        }
    };

    const toggle = (value: boolean) => {
        setOpen(value);
        setStep(0);
        setErrors({});

        // A cancelled edit must not linger into the next one; a saved add starts blank again.
        if (value) {
            const values = valuesFrom(lead);
            setCompany(values.company);
            setEmail(values.email);
            setPhone(values.phone);
            setWebsite(values.website);
            setCity(values.city);
            setNicheId(values.nicheId);
            setNewNiche('');
            setOfferId(values.offerId);
            setStatus(values.status);
            setHook(values.hook);
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
                        Lead
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit lead' : 'New lead'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Change what you know about this business.'
                                : 'Add a business the scraper did not find. Fill what you know; the rest can be enriched later.'}
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
                                <InputError message={errors.company} />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Website"
                                    icon={Globe}
                                    htmlFor="lead-website"
                                >
                                    <Input
                                        id="lead-website"
                                        inputMode="url"
                                        value={website}
                                        onChange={(event) =>
                                            setWebsite(event.target.value)
                                        }
                                        placeholder="example.nl"
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
                                <InputError
                                    message={
                                        errors.niche_id ?? errors.new_niche
                                    }
                                />
                            </Field>

                            <Field
                                label="Offer"
                                icon={Tag}
                                htmlFor="lead-offer"
                            >
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
                                <InputError message={errors.offer_id} />
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
                                    <InputError message={errors.email} />
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
                                    <InputError message={errors.phone} />
                                </Field>
                            </div>

                            <Field label="Status" icon={Flag}>
                                {/* Segmented control: a sunken track, the chosen status raised like the active sidebar item. */}
                                <div
                                    role="radiogroup"
                                    aria-label="Status"
                                    className="flex flex-wrap rounded-md border border-border bg-accent/40 p-0.5"
                                >
                                    {statuses.map((option) => (
                                        <button
                                            key={option.value}
                                            type="button"
                                            role="radio"
                                            aria-checked={
                                                status === option.value
                                            }
                                            onClick={() =>
                                                setStatus(option.value)
                                            }
                                            className={cn(
                                                'flex flex-1 items-center justify-center rounded-[5px] border py-1.5 transition-colors',
                                                editing
                                                    ? 'px-1.5 text-xs'
                                                    : 'px-2 text-sm',
                                                status === option.value
                                                    ? 'border-(--raised-border) bg-background text-foreground shadow-(--raised-shadow)'
                                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {option.label}
                                        </button>
                                    ))}
                                </div>
                                <InputError message={errors.status} />
                            </Field>

                            <Field
                                label="Hook"
                                icon={Sparkles}
                                htmlFor="lead-hook"
                            >
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
                                <InputError message={errors.hook} />
                            </Field>

                            {/* Source is not a choice here: everything from this dialog is manual. */}
                            {!editing && (
                                <p className="text-xs text-muted-foreground">
                                    Added by hand; the enricher can still read
                                    the site for signals.
                                </p>
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        {step === 0 ? (
                            <>
                                <Button
                                    key="cancel"
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    key="next"
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
                                    key="back"
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setStep(0)}
                                >
                                    <ChevronLeft />
                                    Back
                                </Button>
                                <Button
                                    key="submit"
                                    type="submit"
                                    disabled={!ready || processing}
                                >
                                    {editing ? <Check /> : <Plus />}
                                    {editing ? 'Save' : 'Add lead'}
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
