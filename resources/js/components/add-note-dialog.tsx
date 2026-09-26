import { StickyNote } from 'lucide-react';
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

const textareaClassName =
    'min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

/**
 * A note on a lead: what you heard on the phone, what to remember next time.
 * It lands in the Activity tab. Nothing is saved yet.
 */
export function AddNoteDialog({
    company,
    trigger,
    onAdd,
}: {
    company: string;
    trigger: ReactNode;
    onAdd?: (note: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [note, setNote] = useState('');

    const toggle = (value: boolean) => {
        setOpen(value);
        setNote('');
    };

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        onAdd?.(note.trim());
        setOpen(false);
    };

    return (
        <Dialog open={open} onOpenChange={toggle}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>Note on {company}</DialogTitle>
                        <DialogDescription>
                            For yourself and for Claude: context the mails do
                            not carry. Shows up in Activity.
                        </DialogDescription>
                    </DialogHeader>

                    <textarea
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        placeholder="Belde: de praktijkmanager beslist, terug in oktober."
                        aria-label="Note"
                        className={textareaClassName}
                        autoFocus
                    />

                    <DialogFooter>
                        <Button
                            key="cancel"
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button key="submit" type="submit" disabled={note.trim() === ''}>
                            <StickyNote />
                            Add note
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
