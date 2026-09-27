import { useForm } from '@inertiajs/react';
import { Check, Plus, X } from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';
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
import { isBuiltIn } from '@/lib/placeholders';
import { update as updateFacts } from '@/routes/leads/facts';
import type { Lead, Offer } from '@/types';

/** The facts to edit: the sequence's tags first, then any the lead already has. Built-ins come from the lead itself. */
const keysFor = (tags: string[], lead: Lead): string[] =>
    [...tags, ...Object.keys(lead.facts ?? {})].filter(
        (tag, index, all) => !isBuiltIn(tag) && all.indexOf(tag) === index,
    );

const valuesFrom = (keys: string[], lead: Lead): Record<string, string> =>
    Object.fromEntries(keys.map((key) => [key, lead.facts?.[key] ?? '']));

/**
 * The per-lead values behind the {{tags}} in its offer's mails: the compliment,
 * the thing you noticed, whatever the sequence asks for. Saves the whole set;
 * blanks are dropped on the server.
 */
export function LeadFactsDialog({
    lead,
    offer,
    tags,
    trigger,
}: {
    lead: Lead;
    offer: Offer | undefined;
    /** The tags the lead's sequence uses, so every one gets a field even when still empty. */
    tags: string[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [keys, setKeys] = useState(() => keysFor(tags, lead));
    const [customTag, setCustomTag] = useState('');
    const form = useForm<{ facts: Record<string, string> }>({
        facts: valuesFrom(keys, lead),
    });

    const toggle = (value: boolean) => {
        const fresh = keysFor(tags, lead);

        setOpen(value);
        setKeys(fresh);
        setCustomTag('');
        form.setData('facts', valuesFrom(fresh, lead));
        form.clearErrors();
    };

    const setFact = (key: string, value: string) =>
        form.setData('facts', { ...form.data.facts, [key]: value });

    // A custom tag is a new row; the value is typed in the row like the others.
    const normalizedTag = customTag
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_');
    const canAddTag =
        normalizedTag !== '' &&
        !keys.includes(normalizedTag) &&
        !isBuiltIn(normalizedTag);

    const addTag = () => {
        if (!canAddTag) {
            return;
        }

        setKeys([...keys, normalizedTag]);
        setFact(normalizedTag, '');
        setCustomTag('');
    };

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(updateFacts.url(lead.id), {
            preserveScroll: true,
            onSuccess: () => toggle(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={toggle}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>Facts on {lead.company}</DialogTitle>
                        <DialogDescription>
                            {offer
                                ? `What the {{tags}} in the ${offer.name} mails say for this lead.`
                                : 'What the {{tags}} in the mails say for this lead.'}{' '}
                            Company, email, phone, website, city and hook come
                            from the lead itself.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        {keys.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                The sequence uses no custom tags yet. Add one
                                below if a mail needs it.
                            </p>
                        )}
                        {keys.map((key) => (
                            <div key={key} className="grid gap-1.5">
                                <Label
                                    htmlFor={`fact-${key}`}
                                    className="font-mono text-xs text-muted-foreground"
                                >
                                    {`{{${key}}}`}
                                </Label>
                                <Input
                                    id={`fact-${key}`}
                                    value={form.data.facts[key] ?? ''}
                                    onChange={(event) =>
                                        setFact(key, event.target.value)
                                    }
                                    placeholder={
                                        offer?.placeholders?.[key]
                                            ? undefined
                                            : 'Leave blank to drop it'
                                    }
                                />
                                {offer?.placeholders?.[key] && (
                                    <p className="text-xs text-muted-foreground">
                                        {offer.placeholders[key]}
                                    </p>
                                )}
                                <InputError
                                    message={
                                        form.errors[
                                            `facts.${key}` as keyof typeof form.errors
                                        ]
                                    }
                                />
                            </div>
                        ))}

                        <div className="grid gap-1.5 border-t border-border pt-4">
                            <Label
                                htmlFor="fact-new"
                                className="text-xs text-muted-foreground"
                            >
                                Custom tag
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="fact-new"
                                    value={customTag}
                                    onChange={(event) =>
                                        setCustomTag(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            addTag();
                                        }
                                    }}
                                    placeholder="compliment"
                                    className="font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!canAddTag}
                                    onClick={addTag}
                                >
                                    <Plus />
                                    Add
                                </Button>
                            </div>
                            {isBuiltIn(normalizedTag) && (
                                <p className="text-xs text-muted-foreground">
                                    {`{{${normalizedTag}}}`} is filled from the
                                    lead itself.
                                </p>
                            )}
                        </div>
                        <InputError message={form.errors.facts} />
                    </div>

                    <DialogFooter>
                        <Button
                            key="cancel"
                            type="button"
                            variant="ghost"
                            onClick={() => toggle(false)}
                        >
                            <X />
                            Cancel
                        </Button>
                        <Button
                            key="submit"
                            type="submit"
                            disabled={form.processing}
                        >
                            <Check />
                            Save facts
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
