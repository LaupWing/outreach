import { Head, usePage } from '@inertiajs/react';
import { MessageSquare, Send, SlidersHorizontal, Tag } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { MessagePanel } from '@/components/message-panel';
import { messageStatuses } from '@/components/message-status-badge';
import { MessagesTable } from '@/components/messages-table';
import { index as messagesIndex } from '@/routes/messages';
import type { Lead, Mailbox, Message, MessageStatus } from '@/types';

type PageProps = {
    messages: Message[];
    leads: Pick<Lead, 'id' | 'company' | 'email'>[];
    mailboxes: Pick<Mailbox, 'id' | 'address'>[];
};

const statusOptions: FilterOption[] = (
    Object.keys(messageStatuses) as MessageStatus[]
).map((value) => ({ value, label: messageStatuses[value].label }));

const iconClassName = 'size-3.5 text-muted-foreground';

export default function MessagesIndex() {
    const { messages: allMessages, leads, mailboxes: allMailboxes } = usePage<PageProps>().props;
    const mailboxOptions: FilterOption[] = allMailboxes.map((mailbox) => ({
        value: String(mailbox.id),
        label: mailbox.address,
    }));

    const [statuses, setStatuses] = useState<string[]>([]);
    const [mailboxes, setMailboxes] = useState<string[]>([]);
    // Search deep-links here with ?message=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('message');
    const linked = allMessages.find((item) => String(item.id) === linkedId) ?? null;

    const [selected, setSelected] = useState<Message | null>(linked);

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelected(linked);
            setSelected(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps
    // Keeps the last message while the panel slides shut.
    const [panelMessage, setPanelMessage] = useState<Message | null>(linked);

    const messages = allMessages.filter(
        (message) =>
            (statuses.length === 0 || statuses.includes(message.status)) &&
            (mailboxes.length === 0 ||
                mailboxes.includes(String(message.mailbox_id))),
    );

    return (
        <>
            <Head title="Messages" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div data-keeps-panel className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4">
                        <span className="mr-1 flex shrink-0 items-center gap-2 text-sm whitespace-nowrap text-muted-foreground">
                            <SlidersHorizontal className="size-4" />
                            Filters:
                        </span>
                        <FilterMenu
                            label="Status"
                            icon={<Tag className={iconClassName} />}
                            options={statusOptions}
                            selected={statuses}
                            onChange={setStatuses}
                        />
                        <FilterMenu
                            label="Sent from"
                            icon={<Send className={iconClassName} />}
                            options={mailboxOptions}
                            selected={mailboxes}
                            onChange={setMailboxes}
                        />
                    </div>
                    <MessagesTable
                        messages={messages}
                        leads={leads}
                        mailboxes={allMailboxes}
                        selectedId={selected?.id ?? null}
                        onSelect={(message) => {
                            setSelected(message);
                            setPanelMessage(message);
                        }}
                    />
                </div>
                <MessagePanel
                    message={panelMessage}
                    lead={leads.find((lead) => lead.id === panelMessage?.lead_id)}
                    mailbox={allMailboxes.find(
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
