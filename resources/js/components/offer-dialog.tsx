import { router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    FileText,
    Mail,
    Plus,
    Sparkles,
    Tag,
    Target,
    Trash2,
    X,
} from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';
import { DialogSteps } from '@/components/dialog-steps';
import InputError from '@/components/input-error';
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
import { isBuiltIn, tagsIn } from '@/lib/placeholders';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/offers';
import type { Niche, Offer } from '@/types';

/** The offer itself first, its mails second. */
const STEPS = ['Offer', 'Mails'];

/** Sentinel value in the niche select that swaps it for a text field. */
const NEW_NICHE = '__new';

const textareaClassName =
    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const FIRST_SUBJECT = '{{hook_subject}}';
const FIRST_BODY = 'Hoi,\n\n{{hook}}\n\n\n\nLoc';
const FOLLOW_UP_SUBJECT = 'Re: {{hook_subject}}';
const FOLLOW_UP_BODY = 'Hoi,\n\nNog even hierop terugkomen.\n\n\n\nLoc';
const FOLLOW_UP_DAYS = 3;

type Draft = { subject: string; body: string; days_after_previous: number };

const firstMail = (): Draft => ({
    subject: FIRST_SUBJECT,
    body: FIRST_BODY,
    days_after_previous: 0,
});

const followUp = (): Draft => ({
    subject: FOLLOW_UP_SUBJECT,
    body: FOLLOW_UP_BODY,
    days_after_previous: FOLLOW_UP_DAYS,
});

/** Form values for an offer, or the blanks for a new one. */
const valuesFrom = (offer?: Offer) => ({
    name: offer?.name ?? '',
    nicheId: offer ? String(offer.niche_id) : '',
    description: offer?.description ?? '',
});

/**
 * Creates an offer: the thing you test on a niche. Its mails come along in the
 * second step, with an explanation for every custom tag they use so the AI knows
 * what to write per lead. With `offer` it edits the basics instead; the mails
 * then live in the panel's Sequence tab, so the mail step is dropped. Without
 * `niches` it reads them from the page props, so it can sit in a page's static
 * topbar actions.
 */
export function OfferDialog({
    offer,
    niches,
    trigger,
}: {
    offer?: Offer;
    niches?: Niche[];
    /** Replaces the default "+ Offer" button. */
    trigger?: ReactNode;
}) {
    const initial = valuesFrom(offer);
    const editing = offer !== undefined;
    const page = usePage<{ niches?: Niche[] }>();
    const errors = page.props.errors;
    const nicheOptions = niches ?? page.props.niches ?? [];

    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [step, setStep] = useState(0);
    const [name, setName] = useState(initial.name);
    const [nicheId, setNicheId] = useState(initial.nicheId);
    const [newNiche, setNewNiche] = useState('');
    const [description, setDescription] = useState(initial.description);
    const [mails, setMails] = useState<Draft[]>([firstMail()]);
    const [explanations, setExplanations] = useState<Record<string, string>>(
        {},
    );

    // Tags across every subject and body, so a tag used twice is asked about once.
    const tags = tagsIn(
        mails.map((mail) => `${mail.subject}\n${mail.body}`).join('\n'),
    );
    const builtIn = tags.filter(isBuiltIn);
    const custom = tags.filter((tag) => !isBuiltIn(tag));
    const explanationFor = (tag: string) => (explanations[tag] ?? '').trim();

    const hasNiche =
        nicheId === NEW_NICHE ? newNiche.trim() !== '' : nicheId !== '';
    const basicsReady = name.trim() !== '' && hasNiche;
    const mailsReady =
        mails.every(
            (mail) => mail.subject.trim() !== '' && mail.body.trim() !== '',
        ) && custom.every((tag) => explanationFor(tag) !== '');
    const ready = basicsReady && !saving && (editing || mailsReady);

    const reset = () => {
        const values = valuesFrom(offer);
        setStep(0);
        setName(values.name);
        setNicheId(values.nicheId);
        setNewNiche('');
        setDescription(values.description);
        setMails([firstMail()]);
        setExplanations({});
    };

    const changeMail = (index: number, patch: Partial<Draft>) => {
        setMails((current) =>
            current.map((mail, i) =>
                i === index ? { ...mail, ...patch } : mail,
            ),
        );
    };

    const removeMail = (index: number) => {
        setMails((current) => current.filter((_, i) => i !== index));
    };

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        const basics = {
            name,
            description: description.trim() === '' ? null : description,
            ...(nicheId === NEW_NICHE
                ? { new_niche: newNiche.trim() }
                : { niche_id: Number(nicheId) }),
        };
        const options = {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () => {
                setOpen(false);

                if (!editing) {
                    reset();
                }
            },
        };

        if (editing) {
            router.patch(update.url(offer.id), basics, options);
        } else {
            router.post(
                store.url(),
                {
                    ...basics,
                    steps: mails.map((mail, index) => ({
                        subject: mail.subject,
                        body: mail.body,
                        days_after_previous:
                            index === 0 ? 0 : mail.days_after_previous,
                    })),
                    placeholders: Object.fromEntries(
                        custom.map((tag) => [tag, explanationFor(tag)]),
                    ),
                },
                options,
            );
        }
    };

    const toggle = (value: boolean) => {
        setOpen(value);
        setStep(0);

        // A cancelled edit must not linger into the next one.
        if (value && editing) {
            reset();
        }
    };

    const submitLabel =
        mails.length === 0
            ? 'Add offer'
            : `Add offer and ${mails.length} ${mails.length === 1 ? 'mail' : 'mails'}`;

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
                        Offer
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-xl">
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col gap-5"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit offer' : 'New offer'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Change the name, niche or description. The mails live in the Sequence tab.'
                                : 'What you propose to a niche. Leads get an offer, and every mail they receive comes from its sequence.'}
                        </DialogDescription>
                    </DialogHeader>

                    {!editing && (
                        <DialogSteps
                            steps={STEPS}
                            current={step}
                            onSelect={setStep}
                        />
                    )}

                    {/* The body scrolls on its own, so the header and footer stay where they are. */}
                    <div className="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
                        {editing || step === 0 ? (
                            <div className="grid gap-4">
                                <Field
                                    label="Name"
                                    icon={Tag}
                                    htmlFor="offer-name"
                                >
                                    <Input
                                        id="offer-name"
                                        value={name}
                                        onChange={(event) =>
                                            setName(event.target.value)
                                        }
                                        placeholder="Nieuwe website in 2 weken"
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </Field>

                                <Field
                                    label="Niche"
                                    icon={Target}
                                    htmlFor="offer-niche"
                                >
                                    {nicheId === NEW_NICHE ? (
                                        <div className="flex gap-1.5">
                                            <Input
                                                id="offer-niche"
                                                value={newNiche}
                                                onChange={(event) =>
                                                    setNewNiche(
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
                                                id="offer-niche"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Pick a niche" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {nicheOptions.map((niche) => (
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
                                    label="Description"
                                    icon={FileText}
                                    htmlFor="offer-description"
                                >
                                    <textarea
                                        id="offer-description"
                                        value={description}
                                        onChange={(event) =>
                                            setDescription(event.target.value)
                                        }
                                        placeholder="What it is, for whom, and the price."
                                        className={cn(
                                            textareaClassName,
                                            'min-h-16',
                                        )}
                                    />
                                    <InputError message={errors.description} />
                                </Field>
                            </div>
                        ) : (
                            <div className="grid gap-4">
                                {mails.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Without a mail the offer is an idea.
                                    </p>
                                ) : (
                                    <ol className="grid gap-3">
                                        {mails.map((mail, index) => (
                                            <MailCard
                                                key={index}
                                                index={index}
                                                mail={mail}
                                                errors={{
                                                    subject:
                                                        errors[
                                                            `steps.${index}.subject`
                                                        ],
                                                    body: errors[
                                                        `steps.${index}.body`
                                                    ],
                                                    days: errors[
                                                        `steps.${index}.days_after_previous`
                                                    ],
                                                }}
                                                removable={
                                                    index > 0 ||
                                                    mails.length === 1
                                                }
                                                onChange={(patch) =>
                                                    changeMail(index, patch)
                                                }
                                                onRemove={() =>
                                                    removeMail(index)
                                                }
                                            />
                                        ))}
                                    </ol>
                                )}

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="justify-self-start"
                                    onClick={() =>
                                        setMails((current) => [
                                            ...current,
                                            current.length === 0
                                                ? firstMail()
                                                : followUp(),
                                        ])
                                    }
                                >
                                    <Plus />
                                    {mails.length === 0
                                        ? 'Add mail'
                                        : 'Add follow-up'}
                                </Button>

                                {tags.length > 0 && (
                                    <div className="grid gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-3 shadow-(--raised-shadow)">
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <Sparkles className="size-3.5 shrink-0" />
                                            Tags
                                        </p>

                                        {builtIn.length > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                {builtIn.map((tag, index) => (
                                                    <span key={tag}>
                                                        {index > 0 && ', '}
                                                        <Placeholder>
                                                            {tag}
                                                        </Placeholder>
                                                    </span>
                                                ))}{' '}
                                                {builtIn.length === 1
                                                    ? 'is'
                                                    : 'are'}{' '}
                                                filled from the lead.
                                            </p>
                                        )}

                                        {custom.length > 0 && (
                                            <div className="grid gap-3">
                                                <p className="text-xs text-muted-foreground">
                                                    Custom tags are filled by
                                                    the AI per lead; the
                                                    explanation tells it what to
                                                    write.
                                                </p>
                                                {custom.map((tag) => (
                                                    <div
                                                        key={tag}
                                                        className="grid gap-1.5"
                                                    >
                                                        <Label
                                                            htmlFor={`offer-tag-${tag}`}
                                                            className="text-xs text-muted-foreground"
                                                        >
                                                            What should{' '}
                                                            <Placeholder>
                                                                {tag}
                                                            </Placeholder>{' '}
                                                            say?
                                                        </Label>
                                                        <Input
                                                            id={`offer-tag-${tag}`}
                                                            value={
                                                                explanations[
                                                                    tag
                                                                ] ?? ''
                                                            }
                                                            onChange={(event) =>
                                                                setExplanations(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        [tag]: event
                                                                            .target
                                                                            .value,
                                                                    }),
                                                                )
                                                            }
                                                            placeholder="One sentence about something specific on their site"
                                                            className="bg-background"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `placeholders.${tag}`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}

                                <InputError
                                    message={
                                        errors.steps ?? errors.placeholders
                                    }
                                />
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        {editing ? (
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
                                    key="submit"
                                    type="submit"
                                    disabled={!ready}
                                >
                                    <Check />
                                    Save
                                </Button>
                            </>
                        ) : step === 0 ? (
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
                                    disabled={!basicsReady}
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
                                    disabled={!ready}
                                >
                                    <Plus />
                                    {submitLabel}
                                </Button>
                            </>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** One mail in the sequence: its subject and body, plus the wait for follow-ups. */
function MailCard({
    index,
    mail,
    errors,
    removable,
    onChange,
    onRemove,
}: {
    index: number;
    mail: Draft;
    errors: { subject?: string; body?: string; days?: string };
    removable: boolean;
    onChange: (patch: Partial<Draft>) => void;
    onRemove: () => void;
}) {
    const isFirst = index === 0;

    return (
        <li className="flex flex-col gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-3 shadow-(--raised-shadow)">
            <div className="flex items-center gap-2 text-sm">
                <Mail className="size-4 shrink-0 text-muted-foreground" />
                <span className="font-medium">Mail {index + 1}</span>
                {!isFirst && (
                    <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span aria-hidden>·</span>
                        <Clock className="size-3.5 shrink-0" />
                        <Input
                            type="number"
                            min={1}
                            max={60}
                            value={mail.days_after_previous}
                            onChange={(event) =>
                                onChange({
                                    days_after_previous: Number(
                                        event.target.value,
                                    ),
                                })
                            }
                            className="h-7 w-14 bg-background"
                            aria-label="Days after the previous mail"
                        />
                        days after the previous
                    </label>
                )}
                {removable && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="ml-auto size-7 shrink-0 text-muted-foreground hover:text-foreground"
                        aria-label={`Remove mail ${index + 1}`}
                        onClick={onRemove}
                    >
                        <Trash2 />
                    </Button>
                )}
            </div>
            <InputError message={errors.days} />
            <Input
                value={mail.subject}
                onChange={(event) => onChange({ subject: event.target.value })}
                placeholder="Subject"
                aria-label={`Subject of mail ${index + 1}`}
                className="bg-background"
            />
            <InputError message={errors.subject} />
            <textarea
                value={mail.body}
                onChange={(event) => onChange({ body: event.target.value })}
                aria-label={`Body of mail ${index + 1}`}
                className={cn(
                    textareaClassName,
                    'bg-background font-mono text-xs',
                )}
            />
            <InputError message={errors.body} />
        </li>
    );
}

function Placeholder({ children }: { children: string }) {
    return (
        <span className="rounded bg-violet-500/15 px-1 font-mono text-[11px] text-violet-700 dark:text-violet-300">
            {'{{'}
            {children}
            {'}}'}
        </span>
    );
}

function Field({
    label,
    icon: Icon,
    htmlFor,
    children,
}: {
    label: string;
    icon: typeof Tag;
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
