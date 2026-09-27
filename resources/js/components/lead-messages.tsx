import { useForm } from '@inertiajs/react';
import { ChevronRight, Reply, Send } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { store as storeReply } from '@/routes/messages/reply';
import type { Mailbox, Message, MessageStatus } from '@/types';

const statusTones: Record<MessageStatus, { label: string; className: string }> =
    {
        draft: { label: 'Draft', className: 'text-muted-foreground' },
        queued: {
            label: 'Queued',
            className: 'text-violet-600 dark:text-violet-400',
        },
        sent: { label: 'Sent', className: 'text-sky-600 dark:text-sky-400' },
        failed: {
            label: 'Failed',
            className: 'text-red-600 dark:text-red-400',
        },
        bounced: {
            label: 'Bounced',
            className: 'text-red-600 dark:text-red-400',
        },
        replied: {
            label: 'Replied',
            className: 'text-amber-600 dark:text-amber-400',
        },
    };

const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

/** The mails sent to one lead, one per sequence step, with the reply underneath when there is one. */
export function LeadMessages({
    messages,
    mailboxes,
}: {
    messages: Message[];
    mailboxes: Mailbox[];
}) {
    // The newest is the one you want to read first, so it starts open.
    const [openId, setOpenId] = useState<number | null>(
        messages.at(-1)?.id ?? null,
    );
    // Which message has the reply box open, and what is typed in it.
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const form = useForm({ body: '' });
    const draft = form.data.body;
    const setDraft = (value: string) => form.setData('body', value);

    const closeReply = () => {
        setReplyTo(null);
        form.reset();
        form.clearErrors();
    };

    // The reply goes to the message that carries the lead's answer, so it stays in that thread.
    const sendReply = (message: Message) => {
        form.post(storeReply.url(message.id), {
            preserveScroll: true,
            onSuccess: closeReply,
        });
    };

    if (messages.length === 0) {
        return (
            <p className="p-5 text-sm text-muted-foreground">
                No messages yet.
            </p>
        );
    }

    return (
        <div className="flex flex-col">
            {messages.map((message) => {
                const open = message.id === openId;
                const mailbox = mailboxes.find(
                    (item) => item.id === message.mailbox_id,
                );
                const tone = statusTones[message.status];

                return (
                    <div key={message.id} className="border-b border-border">
                        <button
                            type="button"
                            onClick={() => setOpenId(open ? null : message.id)}
                            className="flex w-full items-center gap-3 px-5 py-2.5 text-left text-sm transition-colors hover:bg-accent/40"
                        >
                            <ChevronRight
                                className={cn(
                                    'size-4 shrink-0 text-muted-foreground transition-transform',
                                    open && 'rotate-90',
                                )}
                            />
                            <span className="w-12 shrink-0 text-xs text-muted-foreground">
                                {message.is_reply
                                    ? 'Reply'
                                    : `Step ${message.step}`}
                            </span>
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate font-medium">
                                    {message.subject}
                                </span>
                                {/* Which mailbox sent it; matters once several are connected. */}
                                <span className="truncate text-xs text-muted-foreground">
                                    via {mailbox?.address ?? 'unknown mailbox'}
                                </span>
                            </span>
                            <span
                                className={cn(
                                    'shrink-0 text-xs',
                                    tone.className,
                                )}
                            >
                                {tone.label}
                            </span>
                            <span className="w-24 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                                {message.sent_at
                                    ? dateTime.format(new Date(message.sent_at))
                                    : '—'}
                            </span>
                        </button>

                        {open && (
                            <div className="flex flex-col gap-3 px-5 pb-4 pl-12">
                                <p className="text-sm whitespace-pre-line text-foreground/80">
                                    {message.body}
                                </p>

                                {message.reply && (
                                    <div className="rounded-lg border border-(--raised-border) bg-accent/40 p-3 shadow-(--raised-shadow)">
                                        <div className="mb-1.5 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                                            <Reply className="size-3.5" />
                                            Reply,{' '}
                                            {dateTime.format(
                                                new Date(
                                                    message.reply.received_at,
                                                ),
                                            )}
                                        </div>
                                        <p className="text-sm whitespace-pre-line">
                                            {message.reply.body}
                                        </p>

                                        {/* Answering stays in the same thread and box, so it lands where they wrote from. */}
                                        {replyTo === message.id ? (
                                            <form
                                                className="mt-3 flex flex-col gap-2"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    sendReply(message);
                                                }}
                                            >
                                                <textarea
                                                    value={draft}
                                                    onChange={(event) =>
                                                        setDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    autoFocus
                                                    placeholder={`Hoi,\n\n`}
                                                    aria-label="Reply"
                                                    className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                />
                                                <InputError
                                                    message={form.errors.body}
                                                />
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs text-muted-foreground">
                                                        From{' '}
                                                        {mailbox?.address ??
                                                            'the thread mailbox'}
                                                        , in this thread.
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="ml-auto"
                                                        onClick={closeReply}
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={
                                                            draft.trim() ===
                                                                '' ||
                                                            form.processing
                                                        }
                                                    >
                                                        <Send />
                                                        Send reply
                                                    </Button>
                                                </div>
                                            </form>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="mt-3"
                                                onClick={() =>
                                                    setReplyTo(message.id)
                                                }
                                            >
                                                <Reply />
                                                Reply
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
