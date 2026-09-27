import { useForm } from '@inertiajs/react';
import { StickyNote } from 'lucide-react';
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
import { store as storeNote } from '@/routes/leads/notes';
import type { Lead } from '@/types';

const textareaClassName =
    'min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

/**
 * A note on a lead: what you heard on the phone, what to remember next time.
 * It lands in the Activity tab.
 */
export function AddNoteDialog({
    lead,
    trigger,
}: {
    lead: Lead;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ body: '' });

    const toggle = (value: boolean) => {
        setOpen(value);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeNote.url(lead.id), {
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
                        <DialogTitle>Note on {lead.company}</DialogTitle>
                        <DialogDescription>
                            For yourself and for Claude: context the mails do
                            not carry. Shows up in Activity.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5">
                        <textarea
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            placeholder="Belde: de praktijkmanager beslist, terug in oktober."
                            aria-label="Note"
                            className={textareaClassName}
                            autoFocus
                        />
                        <InputError message={form.errors.body} />
                    </div>

                    <DialogFooter>
                        <Button
                            key="cancel"
                            type="button"
                            variant="ghost"
                            onClick={() => toggle(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            key="submit"
                            type="submit"
                            disabled={
                                form.data.body.trim() === '' || form.processing
                            }
                        >
                            <StickyNote />
                            Add note
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
