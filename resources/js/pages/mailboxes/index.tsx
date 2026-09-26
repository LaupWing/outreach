import { Head } from '@inertiajs/react';
import { Mailbox as MailboxIcon, Plus, SlidersHorizontal, Tag } from 'lucide-react';
import { useState } from 'react';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import { MailboxPanel } from '@/components/mailbox-panel';
import { mailboxStatuses } from '@/components/mailbox-status-badge';
import { MailboxesTable, type MailboxCounts } from '@/components/mailboxes-table';
import { Button } from '@/components/ui/button';
import { mockLeads } from '@/mock/leads';
import { mockMailboxes } from '@/mock/mailboxes';
import { mockMessages } from '@/mock/messages';
import { index as mailboxesIndex } from '@/routes/mailboxes';
import type { Mailbox, MailboxStatus } from '@/types';

const statusOptions: FilterOption[] = (
    Object.keys(mailboxStatuses) as MailboxStatus[]
).map((value) => ({ value, label: mailboxStatuses[value].label }));

// Per mailbox: what went out, what came back, what bounced. Bounces per box show which address lands in spam.
const counts: Record<number, MailboxCounts> = Object.fromEntries(
    mockMailboxes.map((mailbox) => {
        const messages = mockMessages.filter(
            (message) =>
                message.mailbox_id === mailbox.id && message.sent_at !== null,
        );

        return [
            mailbox.id,
            {
                sent: messages.length,
                replied: messages.filter((message) => message.reply !== null)
                    .length,
                bounced: messages.filter(
                    (message) => message.status === 'bounced',
                ).length,
            },
        ];
    }),
);

export default function MailboxesIndex() {
    const [statuses, setStatuses] = useState<string[]>([]);
    const [selected, setSelected] = useState<Mailbox | null>(null);
    // Keeps the last mailbox while the panel slides shut.
    const [panelMailbox, setPanelMailbox] = useState<Mailbox | null>(null);

    const mailboxes = mockMailboxes.filter(
        (mailbox) =>
            statuses.length === 0 || statuses.includes(mailbox.status),
    );

    return (
        <>
            <Head title="Mailboxes" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4">
                        <span className="mr-1 flex shrink-0 items-center gap-2 text-sm whitespace-nowrap text-muted-foreground">
                            <SlidersHorizontal className="size-4" />
                            Filters:
                        </span>
                        <FilterMenu
                            label="Status"
                            icon={<Tag className="size-3.5 text-muted-foreground" />}
                            options={statusOptions}
                            selected={statuses}
                            onChange={setStatuses}
                        />
                    </div>
                    <MailboxesTable
                        mailboxes={mailboxes}
                        counts={counts}
                        selectedId={selected?.id ?? null}
                        onSelect={(mailbox) => {
                            setSelected(mailbox);
                            setPanelMailbox(mailbox);
                        }}
                    />
                </div>
                <MailboxPanel
                    mailbox={panelMailbox}
                    counts={panelMailbox ? counts[panelMailbox.id] : undefined}
                    messages={mockMessages.filter(
                        (message) => message.mailbox_id === panelMailbox?.id,
                    )}
                    leads={mockLeads}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

MailboxesIndex.layout = {
    breadcrumbs: [
        { title: 'Mailboxes', href: mailboxesIndex(), icon: MailboxIcon },
    ],
    actions: (
        <Button variant="ghost" size="sm" className="text-muted-foreground">
            <Plus />
            Mailbox
        </Button>
    ),
};
