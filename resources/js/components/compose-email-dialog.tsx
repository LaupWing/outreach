import {
    ChevronLeft,
    ChevronRight,
    Clock,
    ListOrdered,
    Mail,
    PenLine,
    Send,
    Tag,
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
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { Lead, Mailbox, Message, Offer, SequenceStep } from '@/types';

const STEPS = ['Source', 'Write'];

type Source = 'offer' | 'free';

const textareaClassName =
    'min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

/** Fills the sequence placeholders from the lead. The writer (Claude) does this better later; this is the plain version. */
export function fillPlaceholders(text: string, lead: Lead): string {
    const hookSubject = lead.hook
        ? lead.hook.replace(/\.$/, '').slice(0, 60)
        : `Jullie website, ${lead.company}`;

    return text
        .replaceAll('{{company}}', lead.company)
        .replaceAll('{{hook}}', lead.hook ?? '')
        .replaceAll('{{hook_subject}}', hookSubject);
}

/**
 * Writes one mail to a lead: the next step of its offer, or free-form for a
 * one-off. "Send" only queues; the daily task sends it spread out. Mirrors the
 * MCP tools write_mail and send.
 */
export function ComposeEmailDialog({
    lead,
    offer,
    steps,
    messages,
    mailboxes,
    trigger,
}: {
    lead: Lead;
    offer: Offer | undefined;
    steps: SequenceStep[];
    messages: Message[];
    mailboxes: Mailbox[];
    trigger: ReactNode;
}) {
    // The next step is one past the last one sent to this lead.
    const lastStep = messages.reduce((max, message) => Math.max(max, message.step), 0);
    const nextStep = steps.find((step) => step.step === lastStep + 1);
    const sequenceDone = steps.length > 0 && !nextStep;

    const [open, setOpen] = useState(false);
    const [step, setStep] = useState(0);
    const [source, setSource] = useState<Source>(nextStep ? 'offer' : 'free');
    const [subject, setSubject] = useState('');
    const [body, setBody] = useState('');
    const [mailboxId, setMailboxId] = useState('auto');

    // Boxes that can still send today; "auto" lets the sender pick among them.
    const openBoxes = mailboxes.filter(
        (mailbox) => mailbox.status !== 'paused' && mailbox.sent_today < mailbox.daily_limit,
    );

    const toggle = (value: boolean) => {
        setOpen(value);
        setStep(0);
        setSource(nextStep ? 'offer' : 'free');
        setSubject('');
        setBody('');
        setMailboxId('auto');
    };

    // Moving to the write step loads the draft: the offer's step filled in, or a blank page.
    const startWriting = () => {
        if (source === 'offer' && nextStep) {
            setSubject(fillPlaceholders(nextStep.subject, lead));
            setBody(fillPlaceholders(nextStep.body, lead));
        }

        setStep(1);
    };

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setOpen(false);
    };

    const ready = subject.trim() !== '' && body.trim() !== '' && lead.email !== null;

    return (
        <Dialog open={open} onOpenChange={toggle}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>Email {lead.company}</DialogTitle>
                        <DialogDescription>
                            {lead.email
                                ? `To ${lead.email}. Sending is queued and spread over the day.`
                                : 'No email address on this lead yet; add one before sending.'}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogSteps steps={STEPS} current={step} onSelect={setStep} />

                    {step === 0 ? (
                        <div role="radiogroup" aria-label="Source" className="grid gap-2">
                            <SourceCard
                                active={source === 'offer'}
                                disabled={!nextStep}
                                onClick={() => setSource('offer')}
                                icon={Tag}
                                title={
                                    offer
                                        ? `${offer.name}, step ${lastStep + 1}`
                                        : 'No offer on this lead'
                                }
                                description={
                                    !offer
                                        ? 'Give the lead an offer first, then its sequence can be used.'
                                        : sequenceDone
                                          ? `All ${steps.length} steps were sent. Only a free-form mail is left.`
                                          : nextStep
                                            ? `Subject: ${fillPlaceholders(nextStep.subject, lead)}`
                                            : 'This offer has no steps yet.'
                                }
                            />
                            <SourceCard
                                active={source === 'free'}
                                onClick={() => setSource('free')}
                                icon={PenLine}
                                title="Free-form"
                                description="A one-off mail outside the sequence. Does not move the lead a step forward."
                            />
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            <Field label="Subject" icon={Mail} htmlFor="compose-subject">
                                <Input
                                    id="compose-subject"
                                    value={subject}
                                    onChange={(event) => setSubject(event.target.value)}
                                    placeholder="Subject"
                                    autoFocus={source === 'free'}
                                />
                            </Field>
                            <Field label="Body" icon={PenLine} htmlFor="compose-body">
                                <textarea
                                    id="compose-body"
                                    value={body}
                                    onChange={(event) => setBody(event.target.value)}
                                    placeholder="Hoi,"
                                    className={textareaClassName}
                                />
                            </Field>
                            <Field label="Send from" icon={Send} htmlFor="compose-mailbox">
                                <Select value={mailboxId} onValueChange={setMailboxId}>
                                    <SelectTrigger id="compose-mailbox" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="auto">
                                            Any box with room ({openBoxes.length} available)
                                        </SelectItem>
                                        {mailboxes.map((mailbox) => (
                                            <SelectItem
                                                key={mailbox.id}
                                                value={String(mailbox.id)}
                                                disabled={mailbox.status === 'paused'}
                                            >
                                                {mailbox.address}
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {mailbox.sent_today}/{mailbox.daily_limit} today
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Clock className="size-3.5 shrink-0" />
                                {source === 'offer'
                                    ? `Marks the lead as step ${lastStep + 1} and plans the follow-up.`
                                    : 'Goes out today; the sequence stays where it is.'}
                            </p>
                        </div>
                    )}

                    <DialogFooter>
                        {step === 0 ? (
                            <>
                                <Button key="cancel" type="button" variant="ghost" onClick={() => setOpen(false)}>
                                    Cancel
                                </Button>
                                <Button
                                    key="next"
                                    type="button"
                                    disabled={source === 'offer' && !nextStep}
                                    onClick={startWriting}
                                >
                                    Next
                                    <ChevronRight />
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button key="back" type="button" variant="ghost" onClick={() => setStep(0)}>
                                    <ChevronLeft />
                                    Back
                                </Button>
                                <Button key="submit" type="submit" disabled={!ready}>
                                    <Send />
                                    Queue to send
                                </Button>
                            </>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SourceCard({
    active,
    disabled = false,
    onClick,
    icon: Icon,
    title,
    description,
}: {
    active: boolean;
    disabled?: boolean;
    onClick: () => void;
    icon: typeof Mail;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'flex items-start gap-3 rounded-lg border p-3 text-left transition-colors disabled:opacity-50',
                active
                    ? 'border-(--raised-border) bg-accent/40 shadow-(--raised-shadow)'
                    : 'border-border hover:bg-accent/40',
            )}
        >
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-md',
                    active
                        ? 'bg-linear-to-br from-sky-400 to-violet-500 text-white'
                        : 'bg-accent text-muted-foreground',
                )}
            >
                <Icon className="size-4" />
            </span>
            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="text-sm font-medium">{title}</span>
                <span className="text-xs text-muted-foreground">{description}</span>
            </span>
        </button>
    );
}

function Field({
    label,
    icon: Icon,
    htmlFor,
    children,
}: {
    label: string;
    icon: typeof ListOrdered;
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
