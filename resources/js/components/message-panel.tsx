import { Link } from '@inertiajs/react';
import {
    Calendar,
    ExternalLink,
    ListOrdered,
    Reply,
    Send,
    Tag,
    Users,
} from 'lucide-react';
import { CompanyAvatar } from '@/components/company-avatar';
import { MessageStatusBadge } from '@/components/message-status-badge';
import {
    SidePanel,
    SidePanelEmpty,
    SidePanelHeader,
    SidePanelRow,
} from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import type { MessageWithLead } from '@/components/messages-table';
import { index as leadsIndex } from '@/routes/leads';
import type { Mailbox } from '@/types';

const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
});

/** One mail in full, with its reply underneath; no tabs, a message is small enough to read at once. */
export function MessagePanel({
    message,
    mailbox,
    open,
    onClose,
}: {
    message: MessageWithLead | null;
    mailbox: Pick<Mailbox, 'id' | 'address'> | undefined;
    open: boolean;
    onClose: () => void;
}) {
    const lead = message?.lead;

    return (
        <SidePanel open={open} onClose={onClose}>
            {message && (
                <>
                    <SidePanelHeader
                        parent="Messages"
                        title={message.subject}
                        onClose={onClose}
                    />

                    <div className="flex flex-col gap-4 px-5 pt-5 pb-4">
                        <div className="flex items-center gap-3">
                            <CompanyAvatar
                                name={lead?.company ?? '?'}
                                className="size-12 rounded-lg text-base"
                            />
                            <div className="min-w-0">
                                <h2 className="truncate text-lg font-semibold tracking-tight">
                                    {lead?.company ?? 'Unknown lead'}
                                </h2>
                                <span className="truncate text-sm text-muted-foreground">
                                    {lead?.email ?? 'No email'}
                                </span>
                            </div>
                            <MessageStatusBadge
                                status={message.status}
                                className="ml-auto shrink-0"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={leadsIndex()} prefetch>
                                    <Users />
                                    Open lead
                                </Link>
                            </Button>
                            {/* Gmail finds a mail by its Message-ID; only mails the app sent have one. */}
                            {message.message_id && message.sent_at && (
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={`https://mail.google.com/mail/u/0/#search/rfc822msgid:${encodeURIComponent(message.message_id.replace(/^<|>$/g, ''))}`}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink />
                                        Open in Gmail
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 overflow-auto">
                        <dl className="flex flex-col border-b border-border py-2">
                            <SidePanelRow icon={ListOrdered} label="Step">
                                {message.step}
                            </SidePanelRow>
                            <SidePanelRow icon={Send} label="Sent from">
                                {mailbox?.address ?? <SidePanelEmpty />}
                            </SidePanelRow>
                            <SidePanelRow icon={Calendar} label="Sent">
                                {message.sent_at ? (
                                    dateTime.format(new Date(message.sent_at))
                                ) : message.send_after ? (
                                    <SidePanelEmpty>
                                        Sends{' '}
                                        {dateTime.format(
                                            new Date(message.send_after),
                                        )}
                                    </SidePanelEmpty>
                                ) : (
                                    <SidePanelEmpty>
                                        Not sent yet
                                    </SidePanelEmpty>
                                )}
                            </SidePanelRow>
                            <SidePanelRow icon={Tag} label="Thread">
                                {message.thread_id ?? <SidePanelEmpty />}
                            </SidePanelRow>
                        </dl>

                        <div className="flex flex-col gap-4 p-5">
                            <div>
                                <div className="mb-1.5 text-xs text-muted-foreground uppercase">
                                    {message.subject}
                                </div>
                                <p className="text-sm whitespace-pre-line text-foreground/80">
                                    {message.body}
                                </p>
                            </div>

                            {message.reply ? (
                                <div className="rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow)">
                                    <div className="mb-1.5 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                                        <Reply className="size-3.5" />
                                        Reply,{' '}
                                        {dateTime.format(
                                            new Date(message.reply.received_at),
                                        )}
                                    </div>
                                    <p className="text-sm whitespace-pre-line">
                                        {message.reply.body}
                                    </p>
                                </div>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    {message.status === 'bounced'
                                        ? 'This address bounced; the lead is marked undeliverable.'
                                        : message.status === 'failed'
                                          ? `The mail server refused it: ${message.error ?? 'unknown error'}`
                                          : message.status === 'queued'
                                            ? 'Waiting in the outbox.'
                                            : 'No reply yet.'}
                                </p>
                            )}
                        </div>
                    </div>
                </>
            )}
        </SidePanel>
    );
}
