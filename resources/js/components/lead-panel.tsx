import { router } from '@inertiajs/react';
import {
    Activity,
    Calendar,
    ChevronDown,
    Database,
    FileText,
    ExternalLink,
    Braces,
    Trash2,
    Globe,
    Mail,
    MapPin,
    MessageSquare,
    Pencil,
    Phone,
    Radar,
    Send,
    StickyNote,
    Tag,
    Target,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { ComposeEmailDialog } from '@/components/compose-email-dialog';
import { AddNoteDialog } from '@/components/add-note-dialog';
import { LeadActivity, type LeadNote } from '@/components/lead-activity';
import { LeadDialog } from '@/components/lead-dialog';
import { LeadFactsDialog } from '@/components/lead-facts-dialog';
import { LeadMessages } from '@/components/lead-messages';
import { LeadStatusBadge, leadStatuses } from '@/components/lead-status-badge';
import {
    SidePanel,
    SidePanelEmpty,
    SidePanelHeader,
    SidePanelRow,
    SidePanelTabs,
} from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { tagsIn } from '@/lib/placeholders';
import { cn } from '@/lib/utils';
import { destroy as destroyLead, update as updateLead } from '@/routes/leads';
import type {
    Lead,
    LeadStatus,
    Mailbox,
    Message,
    Niche,
    Offer,
    SequenceStep,
} from '@/types';

/** A lead as the leads and inbox pages receive it: with its notes eager-loaded. */
export type LeadWithNotes = Lead & { notes?: LeadNote[] };

const tabs = [
    { key: 'details', label: 'Details', icon: FileText },
    { key: 'signals', label: 'Signals', icon: Radar },
    { key: 'messages', label: 'Messages', icon: MessageSquare },
    { key: 'activity', label: 'Activity', icon: Activity },
] as const;

export type LeadPanelTab = (typeof tabs)[number]['key'];
type Tab = LeadPanelTab;

const sourceLabels = {
    places: 'Google Places',
    register: 'Register',
    manual: 'Manual',
} as const;

const longDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const formatDate = (value: string | null) =>
    value ? longDate.format(new Date(value)) : null;

/** The lead card on the right: header, actions and the tabs below. */
export function LeadPanel({
    lead,
    niche,
    offer,
    niches = [],
    offers = [],
    steps = [],
    messages,
    mailboxes,
    open,
    onClose,
    initialTab = 'details',
}: {
    lead: LeadWithNotes | null;
    niche: Niche | undefined;
    offer: Offer | undefined;
    /** The full lists feed the edit dialog's selects. */
    niches?: Niche[];
    offers?: Offer[];
    messages: Message[];
    mailboxes: Mailbox[];
    /** Sequence steps of every offer; the compose dialog picks the lead's own. */
    steps?: SequenceStep[];
    open: boolean;
    onClose: () => void;
    /** The inbox opens straight on the thread, the leads page on the details. */
    initialTab?: Tab;
}) {
    const [tab, setTab] = useState<Tab>(initialTab);
    // Delete asks once: the second click within three seconds does it.
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (!confirmingDelete) {
            return;
        }

        const timer = window.setTimeout(() => setConfirmingDelete(false), 3000);

        return () => window.clearTimeout(timer);
    }, [confirmingDelete]);

    useEffect(() => {
        setConfirmingDelete(false);
    }, [lead?.id]);

    const offerId = lead?.offer_id ?? null;
    const status = lead?.status ?? 'new';
    const notes = lead?.notes ?? [];
    const nicheOffers = offers.filter(
        (item) => item.niche_id === lead?.niche_id,
    );
    // The pages that pass no `offers` list still pass the matched `offer`.
    const currentOffer =
        offers.length > 0 ? offers.find((item) => item.id === offerId) : offer;
    // The lead's own sequence; its tags decide which facts the dialog asks for.
    const leadSteps = steps.filter((step) => step.offer_id === offerId);
    const sequenceTags = tagsIn(
        leadSteps.map((step) => `${step.subject}\n${step.body}`).join('\n'),
    );

    // The header dropdowns save straight away; the lead comes back fresh with the page props.
    const patch = (
        data: { status: LeadStatus } | { offer_id: number | null },
    ) => {
        if (lead) {
            router.patch(updateLead.url(lead.id), data, {
                preserveScroll: true,
            });
        }
    };

    const remove = () => {
        if (!lead) {
            return;
        }

        if (!confirmingDelete) {
            setConfirmingDelete(true);

            return;
        }

        router.delete(destroyLead.url(lead.id), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onSuccess: onClose,
        });
    };

    return (
        <SidePanel open={open} onClose={onClose}>
            {lead && (
                <>
                    <SidePanelHeader
                        parent="Leads"
                        title={lead.company}
                        onClose={onClose}
                    />

                    <div className="flex flex-col gap-4 px-5 pt-5 pb-4">
                        <div className="flex items-center gap-3">
                            <CompanyAvatar
                                name={lead.company}
                                className="size-12 rounded-lg text-base"
                            />
                            <div className="min-w-0">
                                <h2 className="truncate text-lg font-semibold tracking-tight">
                                    {lead.company}
                                </h2>
                                {lead.website && (
                                    <a
                                        href={`https://${lead.website}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                                    >
                                        {lead.website}
                                        <ExternalLink className="size-3.5 shrink-0" />
                                    </a>
                                )}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="flex cursor-pointer items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                                        >
                                            {currentOffer
                                                ? `Offer: ${currentOffer.name}`
                                                : 'No offer yet'}
                                            <ChevronDown className="size-3.5 shrink-0" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <DropdownMenuRadioGroup
                                            value={
                                                offerId === null
                                                    ? ''
                                                    : String(offerId)
                                            }
                                            onValueChange={(value) =>
                                                patch({
                                                    offer_id:
                                                        value === ''
                                                            ? null
                                                            : Number(value),
                                                })
                                            }
                                        >
                                            {nicheOffers.map((item) => (
                                                <DropdownMenuRadioItem
                                                    key={item.id}
                                                    value={String(item.id)}
                                                >
                                                    {item.name}
                                                </DropdownMenuRadioItem>
                                            ))}
                                            <DropdownMenuRadioItem
                                                value=""
                                                className="text-muted-foreground"
                                            >
                                                No offer
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="ml-auto flex shrink-0 cursor-pointer items-center gap-1"
                                    >
                                        <LeadStatusBadge status={status} />
                                        <ChevronDown className="size-3.5 shrink-0 text-muted-foreground" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuRadioGroup
                                        value={status}
                                        onValueChange={(value) =>
                                            patch({
                                                status: value as LeadStatus,
                                            })
                                        }
                                    >
                                        {(
                                            Object.keys(
                                                leadStatuses,
                                            ) as LeadStatus[]
                                        ).map((item) => (
                                            <DropdownMenuRadioItem
                                                key={item}
                                                value={item}
                                            >
                                                <LeadStatusBadge
                                                    status={item}
                                                />
                                            </DropdownMenuRadioItem>
                                        ))}
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <div className="flex items-center gap-2">
                            <ComposeEmailDialog
                                lead={lead}
                                offer={currentOffer}
                                steps={leadSteps}
                                messages={messages}
                                mailboxes={mailboxes}
                                trigger={
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={!lead.email}
                                    >
                                        <Mail />
                                        Email
                                    </Button>
                                }
                            />
                            <AddNoteDialog
                                lead={lead}
                                trigger={
                                    <Button variant="outline" size="sm">
                                        <StickyNote />
                                        Add note
                                    </Button>
                                }
                            />
                            <LeadDialog
                                lead={lead}
                                niches={niches}
                                offers={offers}
                                trigger={
                                    <Button variant="outline" size="sm">
                                        <Pencil />
                                        Edit
                                    </Button>
                                }
                            />
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={remove}
                                disabled={deleting}
                                aria-label={
                                    confirmingDelete
                                        ? 'Click again to delete this lead'
                                        : 'Delete this lead'
                                }
                                className={cn(
                                    'ml-auto text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400 dark:hover:text-red-400',
                                    confirmingDelete && 'bg-red-500/10',
                                )}
                            >
                                <Trash2 />
                                {confirmingDelete ? 'Sure?' : 'Delete'}
                            </Button>
                        </div>
                    </div>

                    <SidePanelTabs
                        tabs={tabs.map((item) => ({
                            ...item,
                            badge:
                                item.key === 'messages'
                                    ? messages.length
                                    : undefined,
                        }))}
                        value={tab}
                        onChange={setTab}
                    />

                    <div className="min-h-0 flex-1 overflow-auto">
                        {tab === 'details' && (
                            <dl className="flex flex-col py-2">
                                <SidePanelRow icon={Mail} label="Email">
                                    {lead.email ?? (
                                        <SidePanelEmpty>
                                            No email found
                                        </SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                                <SidePanelRow icon={Phone} label="Phone">
                                    {lead.phone ?? <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={Globe} label="Website">
                                    {lead.website ? (
                                        <a
                                            href={`https://${lead.website}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 hover:underline"
                                        >
                                            {lead.website}
                                            <ExternalLink className="size-3.5 text-muted-foreground" />
                                        </a>
                                    ) : (
                                        <SidePanelEmpty />
                                    )}
                                </SidePanelRow>
                                <SidePanelRow icon={MapPin} label="City">
                                    {lead.city ?? <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={Target} label="Niche">
                                    {niche?.name ?? <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={Tag} label="Offer">
                                    {currentOffer?.name ?? (
                                        <SidePanelEmpty>
                                            No offer yet
                                        </SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                                <SidePanelRow icon={Database} label="Source">
                                    {sourceLabels[lead.source]}
                                </SidePanelRow>
                                <SidePanelRow icon={Send} label="Sent from">
                                    {mailboxes.find(
                                        (mailbox) =>
                                            mailbox.id ===
                                            messages.at(-1)?.mailbox_id,
                                    )?.address ?? (
                                        <SidePanelEmpty>
                                            Not mailed yet
                                        </SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                                <SidePanelRow
                                    icon={Calendar}
                                    label="Last contact"
                                >
                                    {formatDate(lead.last_contact_at) ?? (
                                        <SidePanelEmpty>
                                            Not contacted
                                        </SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                                <SidePanelRow
                                    icon={Calendar}
                                    label="Next action"
                                >
                                    {formatDate(lead.next_action_at) ?? (
                                        <SidePanelEmpty>
                                            Nothing planned
                                        </SidePanelEmpty>
                                    )}
                                </SidePanelRow>

                                <Facts
                                    key={lead.id}
                                    lead={lead}
                                    offer={currentOffer}
                                    tags={sequenceTags}
                                />
                            </dl>
                        )}

                        {tab === 'signals' && <Signals lead={lead} />}

                        {tab === 'messages' && (
                            <LeadMessages
                                messages={messages}
                                mailboxes={mailboxes}
                            />
                        )}

                        {tab === 'activity' && (
                            <LeadActivity
                                lead={lead}
                                messages={messages}
                                notes={notes}
                            />
                        )}
                    </div>
                </>
            )}
        </SidePanel>
    );
}

/** The values behind the sequence's {{tags}} for this lead, with the button to edit them. */
function Facts({
    lead,
    offer,
    tags,
}: {
    lead: Lead;
    offer: Offer | undefined;
    tags: string[];
}) {
    const facts = Object.entries(lead.facts ?? {});

    return (
        <div className="mt-2 flex flex-col border-t border-border pt-2">
            <div className="flex items-center gap-3 px-5 py-2.5 text-sm">
                <span className="flex items-center gap-2 text-muted-foreground">
                    <Braces className="size-4 shrink-0" />
                    Facts
                </span>
                <LeadFactsDialog
                    lead={lead}
                    offer={offer}
                    tags={tags}
                    trigger={
                        <Button
                            variant="outline"
                            size="sm"
                            className="ml-auto h-7"
                        >
                            <Pencil />
                            Edit facts
                        </Button>
                    }
                />
            </div>
            {facts.length === 0 ? (
                <p className="px-5 py-1 text-sm text-muted-foreground/60">
                    No facts yet.
                </p>
            ) : (
                facts.map(([tag, value]) => (
                    <div
                        key={tag}
                        className="flex items-center gap-3 px-5 py-2.5 text-sm"
                    >
                        <dt className="w-32 shrink-0 truncate font-mono text-xs text-muted-foreground">
                            {`{{${tag}}}`}
                        </dt>
                        <dd className="min-w-0 truncate">{value}</dd>
                    </div>
                ))
            )}
        </div>
    );
}

function Signals({ lead }: { lead: Lead }) {
    const signals = lead.signals;

    if (!signals) {
        return <Placeholder>Not enriched yet.</Placeholder>;
    }

    return (
        <div className="flex flex-col gap-4 p-5">
            {/* The hook is what the first email opens with, so it sits on top. */}
            <div className="rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow)">
                <div className="mb-1 text-xs text-muted-foreground uppercase">
                    Hook
                </div>
                <p className="text-sm">
                    {lead.hook ?? (
                        <SidePanelEmpty>No hook written yet</SidePanelEmpty>
                    )}
                </p>
            </div>

            {(signals.blocked || signals.javascript_only) && (
                <div className="rounded-md border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">
                    {signals.blocked
                        ? 'This site blocked the scraper.'
                        : 'This site renders with JavaScript; only the basics were read.'}
                </div>
            )}

            <dl className="-mx-5 flex flex-col">
                <SidePanelRow icon={Calendar} label="Copyright year">
                    {signals.copyright_year ?? <SidePanelEmpty />}
                </SidePanelRow>
                <SidePanelRow icon={Globe} label="Mobile viewport">
                    {signals.viewport === null ? (
                        <SidePanelEmpty />
                    ) : signals.viewport ? (
                        'Yes'
                    ) : (
                        <span className="text-amber-600 dark:text-amber-400">
                            Missing
                        </span>
                    )}
                </SidePanelRow>
                <SidePanelRow icon={Database} label="Software">
                    {signals.software.length > 0 ? (
                        signals.software.join(', ')
                    ) : (
                        <SidePanelEmpty />
                    )}
                </SidePanelRow>
                <SidePanelRow icon={FileText} label="Last news">
                    {formatDate(signals.last_news_at) ?? (
                        <SidePanelEmpty>No news found</SidePanelEmpty>
                    )}
                </SidePanelRow>
            </dl>
        </div>
    );
}

function Placeholder({ children }: { children: ReactNode }) {
    return <p className="p-5 text-sm text-muted-foreground">{children}</p>;
}
