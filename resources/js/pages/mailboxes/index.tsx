import { Head, usePage } from '@inertiajs/react';
import { Mailbox as MailboxIcon, SlidersHorizontal, Tag } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { FilterMenu } from '@/components/filters/filter-menu';
import type { FilterOption } from '@/components/filters/filter-trigger';
import {
    MailboxPanel,
    type MailboxLead,
    type MailboxMessage,
} from '@/components/mailbox-panel';
import { mailboxStatuses } from '@/components/mailbox-status-badge';
import { MailboxesTable, type MailboxCounts } from '@/components/mailboxes-table';
import { MailboxDialog } from '@/components/mailbox-dialog';
import { index as mailboxesIndex } from '@/routes/mailboxes';
import type { Mailbox, MailboxStatus } from '@/types';

type PageProps = {
    mailboxes: Mailbox[];
    /** Every message with a mailbox, lean: enough to count sent, replies and bounces per box. */
    messages: MailboxMessage[];
    leads: MailboxLead[];
};

const statusOptions: FilterOption[] = (
    Object.keys(mailboxStatuses) as MailboxStatus[]
).map((value) => ({ value, label: mailboxStatuses[value].label }));

export default function MailboxesIndex() {
    const { mailboxes: allMailboxes, messages, leads } =
        usePage<PageProps>().props;
    const [statuses, setStatuses] = useState<string[]>([]);

    // Per mailbox: what went out, what came back, what bounced. Bounces per box show which address lands in spam.
    const counts = useMemo<Record<number, MailboxCounts>>(
        () =>
            Object.fromEntries(
                allMailboxes.map((mailbox) => {
                    const sent = messages.filter(
                        (message) =>
                            message.mailbox_id === mailbox.id &&
                            message.sent_at !== null,
                    );

                    return [
                        mailbox.id,
                        {
                            sent: sent.length,
                            replied: sent.filter(
                                (message) => message.reply !== null,
                            ).length,
                            bounced: sent.filter(
                                (message) => message.status === 'bounced',
                            ).length,
                        },
                    ];
                }),
            ),
        [allMailboxes, messages],
    );

    // Search deep-links here with ?mailbox=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('mailbox');
    const linked =
        allMailboxes.find((item) => String(item.id) === linkedId) ?? null;

    const [selected, setSelected] = useState<Mailbox | null>(linked);
    // Keeps the last mailbox while the panel slides shut.
    const [panelMailbox, setPanelMailbox] = useState<Mailbox | null>(linked);

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelected(linked);
            setPanelMailbox(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps

    // After a save the props are fresh; the open panel should show the same row, not a stale copy.
    useEffect(() => {
        setPanelMailbox((current) =>
            current
                ? (allMailboxes.find((item) => item.id === current.id) ?? current)
                : current,
        );
        setSelected((current) =>
            current
                ? (allMailboxes.find((item) => item.id === current.id) ?? null)
                : current,
        );
    }, [allMailboxes]);

    const mailboxes = allMailboxes.filter(
        (mailbox) =>
            statuses.length === 0 || statuses.includes(mailbox.status),
    );

    return (
        <>
            <Head title="Mailboxes" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div data-keeps-panel className="flex h-12 shrink-0 items-center gap-2 overflow-x-auto border-b border-border px-4">
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
                    messages={messages.filter(
                        (message) => message.mailbox_id === panelMailbox?.id,
                    )}
                    leads={leads}
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
    actions: <MailboxDialog />,
};
