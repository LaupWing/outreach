import {
    Check,
    ChevronLeft,
    ChevronRight,
    FileText,
    Mail,
    Plus,
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
import type { Niche, Offer } from '@/types';

/** The offer itself first, the first mail second. */
const STEPS = ['Offer', 'First mail'];

/** Sentinel value in the niche select that swaps it for a text field. */
const NEW_NICHE = '__new';

const textareaClassName =
    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

/** Form values for an offer, or the blanks for a new one. */
const valuesFrom = (offer?: Offer) => ({
    name: offer?.name ?? '',
    nicheId: offer ? String(offer.niche_id) : '',
    description: offer?.description ?? '',
});

/**
 * Creates an offer: the thing you test on a niche. The first mail can come along;
 * the rest of the sequence is added in the offer's panel. With `offer` it edits
 * the basics instead; the mails then live in the panel's Sequence tab, so the
 * mail step is dropped. Nothing is saved yet.
 */
export function OfferDialog({
    offer,
    niches,
    trigger,
}: {
    offer?: Offer;
    niches: Niche[];
    /** Replaces the default "+ Offer" button. */
    trigger?: ReactNode;
}) {
    const initial = valuesFrom(offer);
    const editing = offer !== undefined;

    const [open, setOpen] = useState(false);
    const [step, setStep] = useState(0);
    const [name, setName] = useState(initial.name);
    const [nicheId, setNicheId] = useState(initial.nicheId);
    const [newNiche, setNewNiche] = useState('');
    const [description, setDescription] = useState(initial.description);
    const [withFirstMail, setWithFirstMail] = useState(true);
    const [subject, setSubject] = useState('{{hook_subject}}');
    const [body, setBody] = useState(
        'Hoi,\n\n{{hook}}\n\n\n\nLoc',
    );

    const hasNiche =
        nicheId === NEW_NICHE ? newNiche.trim() !== '' : nicheId !== '';
    const basicsReady = name.trim() !== '' && hasNiche;
    const ready =
        basicsReady &&
        (editing ||
            !withFirstMail ||
            (subject.trim() !== '' && body.trim() !== ''));

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setOpen(false);
    };

    const toggle = (value: boolean) => {
        setOpen(value);
        setStep(0);

        // A cancelled edit must not linger into the next one.
        if (value && editing) {
            const values = valuesFrom(offer);
            setName(values.name);
            setNicheId(values.nicheId);
            setNewNiche('');
            setDescription(values.description);
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
                        Offer
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-5">
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

                    {editing || step === 0 ? (
                        <div className="grid gap-4">
                            <Field label="Name" icon={Tag} htmlFor="offer-name">
                                <Input
                                    id="offer-name"
                                    value={name}
                                    onChange={(event) => setName(event.target.value)}
                                    placeholder="Nieuwe website in 2 weken"
                                    autoFocus
                                />
                            </Field>

                            <Field label="Niche" icon={Target} htmlFor="offer-niche">
                                {nicheId === NEW_NICHE ? (
                                    <div className="flex gap-1.5">
                                        <Input
                                            id="offer-niche"
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
                                    <Select value={nicheId} onValueChange={setNicheId}>
                                        <SelectTrigger id="offer-niche" className="w-full">
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
                                    className={cn(textareaClassName, 'min-h-16')}
                                />
                            </Field>

                        </div>
                    ) : (
                        <div className="grid gap-4">
                            {/* The first mail is optional here; without one the offer is an idea, not something you can send. */}
                            <div className="flex flex-col gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-3 shadow-(--raised-shadow)">
                                <label className="flex cursor-pointer items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={withFirstMail}
                                        onChange={(event) =>
                                            setWithFirstMail(event.target.checked)
                                        }
                                        className="size-4 accent-violet-500"
                                    />
                                    <Mail className="size-4 shrink-0 text-muted-foreground" />
                                    Write the first mail now
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        step 1
                                    </span>
                                </label>

                                {withFirstMail && (
                                    <div className="grid gap-3">
                                        <Input
                                            value={subject}
                                            onChange={(event) =>
                                                setSubject(event.target.value)
                                            }
                                            placeholder="Subject"
                                            aria-label="Subject"
                                            className="bg-background"
                                        />
                                        <textarea
                                            value={body}
                                            onChange={(event) => setBody(event.target.value)}
                                            aria-label="Body"
                                            className={cn(
                                                textareaClassName,
                                                'bg-background font-mono text-xs',
                                            )}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Placeholders:{' '}
                                            <Placeholder>hook</Placeholder>,{' '}
                                            <Placeholder>hook_subject</Placeholder> and{' '}
                                            <Placeholder>company</Placeholder> are filled
                                            per lead.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

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
                                <Button key="submit" type="submit" disabled={!ready}>
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
                                <Button key="submit" type="submit" disabled={!ready}>
                                    <Plus />
                                    {withFirstMail ? 'Add offer and mail' : 'Add offer'}
                                </Button>
                            </>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
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
