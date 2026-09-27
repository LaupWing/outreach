import { Head, router, usePage } from '@inertiajs/react';
import { MessageSquare, Send, SlidersHorizontal, Tag } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { MessagePanel } from '@/components/message-panel';
import { messageStatuses } from '@/components/message-status-badge';
import {
    MessagesTable,
    type MessageCounts,
    type MessageWithLead,
} from '@/components/messages-table';
import { index as messagesIndex } from '@/routes/messages';
import type { Mailbox, MessageStatus } from '@/types';

type PageProps = {
    messages: { data: MessageWithLead[] };
    counts: MessageCounts;
    /** The ?message deep link, fetched on its own in case it sits past the loaded page. */
    linked: MessageWithLead | null;
    mailboxes: Pick<Mailbox, 'id' | 'address'>[];
};

const statusOptions: FilterOption[] = (
    Object.keys(messageStatuses) as MessageStatus[]
).map((value) => ({ value, label: messageStatuses[value].label }));

const iconClassName = 'size-3.5 text-muted-foreground';

export default function MessagesIndex() {
    const {
        messages,
        counts,
        linked: linkedProp,
        mailboxes,
    } = usePage<PageProps>().props;
    const mailboxOptions: FilterOption[] = mailboxes.map((mailbox) => ({
        value: String(mailbox.id),
        label: mailbox.address,
    }));

    // The filters live in the URL, so a reload and the next page keep them.
    const { url } = usePage();
    const params = new URLSearchParams(url.split('?')[1] ?? '');
    const statuses = params.getAll('status[]');
    const mailboxIds = params.getAll('mailbox[]');
    // Search deep-links here with ?message=ID.
    const linkedId = params.get('message');
    const linked =
        linkedProp ??
        messages.data.find((item) => String(item.id) === linkedId) ??
        null;

    const applyFilters = (next: { status?: string[]; mailbox?: string[] }) =>
        router.get(
            messagesIndex(),
            {
                status: next.status ?? statuses,
                mailbox: next.mailbox ?? mailboxIds,
                message: linkedId ?? undefined,
            },
            {
                only: ['messages', 'counts'],
                reset: ['messages'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );

    const [selected, setSelected] = useState<MessageWithLead | null>(linked);
    // Keeps the last message while the panel slides shut.
    const [panelMessage, setPanelMessage] = useState<MessageWithLead | null>(
        linked,
    );

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelected(linked);
            setPanelMessage(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title="Messages" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div
                        data-keeps-panel
                        className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4"
                    >
                        <span className="mr-1 flex shrink-0 items-center gap-2 text-sm whitespace-nowrap text-muted-foreground">
                            <SlidersHorizontal className="size-4" />
                            Filters:
                        </span>
                        <FilterMenu
                            label="Status"
                            icon={<Tag className={iconClassName} />}
                            options={statusOptions}
                            selected={statuses}
                            onChange={(status) => applyFilters({ status })}
                        />
                        <FilterMenu
                            label="Sent from"
                            icon={<Send className={iconClassName} />}
                            options={mailboxOptions}
                            selected={mailboxIds}
                            onChange={(mailbox) => applyFilters({ mailbox })}
                        />
                    </div>
                    <MessagesTable
                        messages={messages.data}
                        counts={counts}
                        mailboxes={mailboxes}
                        selectedId={selected?.id ?? null}
                        onSelect={(message) => {
                            setSelected(message);
                            setPanelMessage(message);
                        }}
                    />
                </div>
                <MessagePanel
                    message={panelMessage}
                    mailbox={mailboxes.find(
                        (mailbox) => mailbox.id === panelMessage?.mailbox_id,
                    )}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

MessagesIndex.layout = {
    breadcrumbs: [
        { title: 'Messages', href: messagesIndex(), icon: MessageSquare },
    ],
};
