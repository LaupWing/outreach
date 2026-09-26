import { Head } from '@inertiajs/react';
import { MessageSquare, Send, SlidersHorizontal, Tag } from 'lucide-react';
import { useState } from 'react';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { MessagePanel } from '@/components/message-panel';
import { messageStatuses } from '@/components/message-status-badge';
import { MessagesTable } from '@/components/messages-table';
import { mockLeads } from '@/mock/leads';
import { mockMailboxes } from '@/mock/mailboxes';
import { mockMessages } from '@/mock/messages';
import { index as messagesIndex } from '@/routes/messages';
import type { Message, MessageStatus } from '@/types';

const statusOptions: FilterOption[] = (
    Object.keys(messageStatuses) as MessageStatus[]
).map((value) => ({ value, label: messageStatuses[value].label }));

const mailboxOptions: FilterOption[] = mockMailboxes.map((mailbox) => ({
    value: String(mailbox.id),
    label: mailbox.address,
}));

// Newest first: the last thing that went out is the first thing you want to see.
const sorted = [...mockMessages].sort((a, b) =>
    (b.sent_at ?? '').localeCompare(a.sent_at ?? ''),
);

const iconClassName = 'size-3.5 text-muted-foreground';

export default function MessagesIndex() {
    const [statuses, setStatuses] = useState<string[]>([]);
    const [mailboxes, setMailboxes] = useState<string[]>([]);
    const [selected, setSelected] = useState<Message | null>(null);
    // Keeps the last message while the panel slides shut.
    const [panelMessage, setPanelMessage] = useState<Message | null>(null);

    const messages = sorted.filter(
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
                    <div className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4">
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
                        leads={mockLeads}
                        mailboxes={mockMailboxes}
                        selectedId={selected?.id ?? null}
                        onSelect={(message) => {
                            setSelected(message);
                            setPanelMessage(message);
                        }}
                    />
                </div>
                <MessagePanel
                    message={panelMessage}
                    lead={mockLeads.find((lead) => lead.id === panelMessage?.lead_id)}
                    mailbox={mockMailboxes.find(
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
