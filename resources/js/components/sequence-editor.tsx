import {
    ArrowDown,
    ArrowUp,
    Check,
    ChevronRight,
    Clock,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useState, type SubmitEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { SequenceStep } from '@/types';

const textareaClassName =
    'min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

type Draft = { subject: string; body: string; days_after_previous: number };

const emptyDraft = (isFirst: boolean): Draft => ({
    subject: isFirst ? '{{hook_subject}}' : 'Re: {{hook_subject}}',
    body: isFirst ? 'Hoi,\n\n{{hook}}\n\n\n\nLoc' : 'Hoi,\n\nNog even hierop terugkomen.\n\n\n\nLoc',
    days_after_previous: isFirst ? 0 : 4,
});

/**
 * The mails of an offer, editable in place: add, change, reorder, delete. State is
 * local while we mock; each action becomes one call once the steps live in Laravel.
 */
export function SequenceEditor({
    offerId,
    steps: initial,
}: {
    offerId: number;
    steps: SequenceStep[];
}) {
    const [steps, setSteps] = useState<SequenceStep[]>(initial);
    const [openId, setOpenId] = useState<number | null>(initial[0]?.id ?? null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);

    // Step numbers follow the order in the list; renumber after every change.
    const commit = (next: SequenceStep[]) =>
        setSteps(next.map((step, index) => ({ ...step, step: index + 1 })));

    const add = (draft: Draft) => {
        const id = Math.max(0, ...steps.map((step) => step.id)) + 1;

        commit([...steps, { id, offer_id: offerId, step: steps.length + 1, ...draft }]);
        setAdding(false);
        setOpenId(id);
    };

    const update = (id: number, draft: Draft) => {
        commit(steps.map((step) => (step.id === id ? { ...step, ...draft } : step)));
        setEditingId(null);
    };

    const remove = (id: number) => {
        commit(steps.filter((step) => step.id !== id));
        setEditingId(null);
    };

    const move = (index: number, direction: -1 | 1) => {
        const next = [...steps];
        const [step] = next.splice(index, 1);

        next.splice(index + direction, 0, step);
        commit(next);
    };

    return (
        <div className="flex flex-col p-5">
            {steps.length === 0 && !adding && (
                <p className="mb-4 text-sm text-muted-foreground">
                    No steps yet. An offer needs at least one mail before it can be sent.
                </p>
            )}

            <ol className="flex flex-col">
                {steps.map((step, index) => {
                    const isOpen = step.id === openId;
                    const isEditing = step.id === editingId;

                    return (
                        <li key={step.id} className="flex flex-col">
                            {index > 0 && (
                                <div className="flex items-center gap-3 py-1 pl-2.5">
                                    <span className="h-6 w-px bg-border" />
                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Clock className="size-3" />
                                        wait {step.days_after_previous} days
                                    </span>
                                </div>
                            )}

                            <div className="rounded-lg border border-(--raised-border) bg-accent/40 shadow-(--raised-shadow)">
                                {isEditing ? (
                                    <StepForm
                                        initial={step}
                                        isFirst={index === 0}
                                        onSave={(draft) => update(step.id, draft)}
                                        onCancel={() => setEditingId(null)}
                                        onDelete={() => remove(step.id)}
                                    />
                                ) : (
                                    <>
                                        <div className="flex items-center gap-1 pr-2">
                                            <button
                                                type="button"
                                                onClick={() => setOpenId(isOpen ? null : step.id)}
                                                className="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5 text-left text-sm"
                                            >
                                                <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-sky-400 to-violet-500 text-[10px] font-semibold text-white">
                                                    {step.step}
                                                </span>
                                                <span className="min-w-0 flex-1 truncate font-medium">
                                                    {step.subject}
                                                </span>
                                                <ChevronRight
                                                    className={cn(
                                                        'size-4 shrink-0 text-muted-foreground transition-transform',
                                                        isOpen && 'rotate-90',
                                                    )}
                                                />
                                            </button>
                                            {/* Reorder and edit live on the row, so the sequence reads as a list you shape. */}
                                            <IconButton
                                                label="Move up"
                                                disabled={index === 0}
                                                onClick={() => move(index, -1)}
                                            >
                                                <ArrowUp />
                                            </IconButton>
                                            <IconButton
                                                label="Move down"
                                                disabled={index === steps.length - 1}
                                                onClick={() => move(index, 1)}
                                            >
                                                <ArrowDown />
                                            </IconButton>
                                            <IconButton
                                                label="Edit step"
                                                onClick={() => {
                                                    setEditingId(step.id);
                                                    setOpenId(step.id);
                                                }}
                                            >
                                                <Pencil />
                                            </IconButton>
                                        </div>
                                        {isOpen && (
                                            <p className="border-t border-border px-3 py-3 text-sm whitespace-pre-line text-foreground/80">
                                                <Placeholders text={step.body} />
                                            </p>
                                        )}
                                    </>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>

            {adding ? (
                <div className="mt-3 rounded-lg border border-(--raised-border) bg-accent/40 shadow-(--raised-shadow)">
                    <StepForm
                        initial={emptyDraft(steps.length === 0)}
                        isFirst={steps.length === 0}
                        onSave={add}
                        onCancel={() => setAdding(false)}
                    />
                </div>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-3 self-start"
                    onClick={() => {
                        setAdding(true);
                        setEditingId(null);
                    }}
                >
                    <Plus />
                    Step {steps.length + 1}
                </Button>
            )}
        </div>
    );
}

/** One step's fields; the same form adds and edits. */
function StepForm({
    initial,
    isFirst,
    onSave,
    onCancel,
    onDelete,
}: {
    initial: Draft;
    isFirst: boolean;
    onSave: (draft: Draft) => void;
    onCancel: () => void;
    onDelete?: () => void;
}) {
    const [subject, setSubject] = useState(initial.subject);
    const [body, setBody] = useState(initial.body);
    const [days, setDays] = useState(initial.days_after_previous);

    const ready = subject.trim() !== '' && body.trim() !== '';

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        onSave({ subject, body, days_after_previous: isFirst ? 0 : days });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-3 p-3">
            {!isFirst && (
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Clock className="size-3.5 shrink-0" />
                    Wait
                    <Input
                        type="number"
                        min={1}
                        max={60}
                        value={days}
                        onChange={(event) => setDays(Number(event.target.value))}
                        className="h-7 w-16 bg-background"
                        aria-label="Days after the previous step"
                    />
                    days after the previous mail
                </label>
            )}
            <Input
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
                placeholder="Subject"
                aria-label="Subject"
                className="bg-background"
                autoFocus
            />
            <textarea
                value={body}
                onChange={(event) => setBody(event.target.value)}
                aria-label="Body"
                className={textareaClassName}
            />
            <div className="flex items-center gap-1">
                {onDelete && (
                    <Button
                        key="delete"
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-red-600 hover:text-red-600 dark:text-red-400 dark:hover:text-red-400"
                        onClick={onDelete}
                    >
                        <Trash2 />
                        Delete
                    </Button>
                )}
                <Button
                    key="cancel"
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="ml-auto"
                    onClick={onCancel}
                >
                    <X />
                    Cancel
                </Button>
                <Button key="submit" type="submit" size="sm" disabled={!ready}>
                    <Check />
                    Save
                </Button>
            </div>
        </form>
    );
}

function IconButton({
    label,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 shrink-0 text-muted-foreground hover:text-foreground"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

/** Highlights the {{placeholders}} the writer fills per lead. */
export function Placeholders({ text }: { text: string }) {
    return (
        <>
            {text.split(/(\{\{[a-z_]+\}\})/).map((part, index) =>
                part.startsWith('{{') ? (
                    <span
                        key={index}
                        className="rounded bg-violet-500/15 px-1 font-mono text-xs text-violet-700 dark:text-violet-300"
                    >
                        {part.slice(2, -2)}
                    </span>
                ) : (
                    part
                ),
            )}
        </>
    );
}
