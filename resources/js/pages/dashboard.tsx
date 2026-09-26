import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarClock,
    House,
    Mailbox as MailboxIcon,
    Radar,
    Reply,
    Tag,
    Target,
} from 'lucide-react';
import { RateList, type RateRow } from '@/components/home/rate-list';
import { gradeRate, StatCard } from '@/components/home/stat-card';
import { dashboard } from '@/routes';
import { index as inboxIndex } from '@/routes/inbox';
import { index as mailboxesIndex } from '@/routes/mailboxes';
import { index as nichesIndex } from '@/routes/niches';
import { index as offersIndex } from '@/routes/offers';
import { index as scrapeIndex } from '@/routes/scrape';
import type { Lead, Mailbox, Message, Niche, Offer, PlacesUsage } from '@/types';

type PageProps = {
    leads: Pick<Lead, 'id' | 'niche_id' | 'offer_id' | 'status' | 'next_action_at'>[];
    messages: Pick<Message, 'id' | 'lead_id' | 'mailbox_id' | 'status' | 'sent_at' | 'reply'>[];
    mailboxes: Mailbox[];
    niches: Pick<Niche, 'id' | 'name'>[];
    offers: Pick<Offer, 'id' | 'name'>[];
    usage: PlacesUsage;
    due: number;
};

export default function Dashboard() {
    const props = usePage<PageProps>().props;
    const { leads, messages, mailboxes, niches, offers, usage } = props;

    const sent = messages.filter((message) => message.sent_at !== null);
    const emailedLeadIds = new Set(sent.map((message) => message.lead_id));
    const repliedLeadIds = new Set(
        sent.filter((message) => message.reply).map((message) => message.lead_id),
    );

    const emailed = emailedLeadIds.size;
    const replied = repliedLeadIds.size;
    const responseRate = emailed > 0 ? Math.round((replied / emailed) * 100) : null;
    const customers = leads.filter((lead) => lead.status === 'customer').length;

    // The inbox's three queues, counted here so Home can say what waits on you.
    const replies = leads.filter((lead) => lead.status === 'replied').length;
    // Due is compared against now on the server; the other two are plain status counts.
    const due = props.due;
    const bounces = leads.filter((lead) => lead.status === 'undeliverable').length;

    const sentToday = mailboxes.reduce((sum, mailbox) => sum + mailbox.sent_today, 0);
    const roomToday = mailboxes
        .filter((mailbox) => mailbox.status !== 'paused')
        .reduce((sum, mailbox) => sum + mailbox.daily_limit, 0);

    // Response per offer, niche and mailbox: replied over emailed, counted on leads.
    const byLeads = (leadIds: number[]) => ({
        emailed: leadIds.filter((id) => emailedLeadIds.has(id)).length,
        replied: leadIds.filter((id) => repliedLeadIds.has(id)).length,
    });

    const offerRows: RateRow[] = offers.map((offer) => ({
        id: offer.id,
        name: offer.name,
        href: offersIndex({ query: { offer: offer.id } }),
        ...byLeads(
            leads.filter((lead) => lead.offer_id === offer.id).map((lead) => lead.id),
        ),
    }));

    const nicheRows: RateRow[] = niches.map((niche) => ({
        id: niche.id,
        name: niche.name,
        href: nichesIndex({ query: { niche: niche.id } }),
        ...byLeads(
            leads.filter((lead) => lead.niche_id === niche.id).map((lead) => lead.id),
        ),
    }));

    const mailboxRows: RateRow[] = mailboxes.map((mailbox) => {
        const own = sent.filter((message) => message.mailbox_id === mailbox.id);
        const bounced = own.filter((message) => message.status === 'bounced').length;

        return {
            id: mailbox.id,
            name: mailbox.address,
            href: mailboxesIndex({ query: { mailbox: mailbox.id } }),
            emailed: own.length,
            replied: own.filter((message) => message.reply).length,
            // Bounces per box are the spam signal the briefing asks for.
            note:
                bounced > 0 ? (
                    <span className="flex shrink-0 items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                        <AlertTriangle className="size-3" />
                        {bounced}
                    </span>
                ) : undefined,
        };
    });

    const waiting = replies + due + bounces;

    return (
        <>
            <Head title="Home" />

            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-auto p-4">
                {/* Today: what waits on you and how much sending room is left. */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Link
                        href={inboxIndex()}
                        prefetch
                        className="flex items-center gap-4 rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow) transition-colors hover:bg-accent"
                    >
                        <span className="lava bg-clip-text text-3xl font-semibold text-transparent tabular-nums">
                            {waiting}
                        </span>
                        <span className="flex min-w-0 flex-1 flex-col">
                            <span className="text-sm">Waiting on you</span>
                            <span className="flex gap-3 text-xs text-muted-foreground tabular-nums">
                                <span className="flex items-center gap-1">
                                    <Reply className="size-3" /> {replies}
                                </span>
                                <span className="flex items-center gap-1">
                                    <CalendarClock className="size-3" /> {due}
                                </span>
                                <span className="flex items-center gap-1">
                                    <AlertTriangle className="size-3" /> {bounces}
                                </span>
                            </span>
                        </span>
                        <ArrowUpRight className="size-4 shrink-0 text-muted-foreground" />
                    </Link>

                    <Link
                        href={mailboxesIndex()}
                        prefetch
                        className="flex items-center gap-4 rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow) transition-colors hover:bg-accent"
                    >
                        <span className="text-3xl font-semibold tabular-nums">
                            {sentToday}
                            <span className="text-sm font-normal text-muted-foreground">
                                {' '}
                                / {roomToday}
                            </span>
                        </span>
                        <span className="flex min-w-0 flex-1 flex-col">
                            <span className="text-sm">Sent today</span>
                            <span className="text-xs text-muted-foreground">
                                across{' '}
                                {mailboxes.filter((m) => m.status !== 'paused').length}{' '}
                                mailboxes
                            </span>
                        </span>
                        <ArrowUpRight className="size-4 shrink-0 text-muted-foreground" />
                    </Link>

                    <Link
                        href={scrapeIndex()}
                        prefetch
                        className="flex items-center gap-4 rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow) transition-colors hover:bg-accent"
                    >
                        <span className="text-3xl font-semibold tabular-nums">
                            {usage.free_limit - usage.used}
                        </span>
                        <span className="flex min-w-0 flex-1 flex-col">
                            <span className="text-sm">Free scrape requests left</span>
                            <span className="text-xs text-muted-foreground">
                                about{' '}
                                {((usage.free_limit - usage.used) * 20).toLocaleString('en-GB')}{' '}
                                businesses this month
                            </span>
                        </span>
                        <ArrowUpRight className="size-4 shrink-0 text-muted-foreground" />
                    </Link>
                </div>

                {/* The numbers that tell whether the outreach works at all. */}
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Response rate"
                        value={responseRate === null ? '—' : `${responseRate}%`}
                        grade={gradeRate(responseRate)}
                        tone={responseRate !== null && responseRate >= 15 ? 'good' : 'plain'}
                        description="Share of emailed leads that wrote back, across every offer."
                    />
                    <StatCard
                        title="Emailed"
                        value={String(emailed)}
                        description={`${leads.length} leads in total; the rest are new or without an email.`}
                    />
                    <StatCard
                        title="Replied"
                        value={String(replied)}
                        description="Leads with at least one reply, whatever they answered."
                    />
                    <StatCard
                        title="Customers"
                        value={String(customers)}
                        tone={customers > 0 ? 'good' : 'plain'}
                        description={
                            emailed > 0
                                ? `${Math.round((customers / emailed) * 100)}% of emailed leads became a customer.`
                                : 'Nobody emailed yet.'
                        }
                    />
                </div>

                {/* Per offer, per niche, per mailbox: the three cuts the briefing asks for. */}
                <div className="grid gap-4 xl:grid-cols-3">
                    <RateList title="Response per offer" icon={Tag} rows={offerRows} href={offersIndex()} />
                    <RateList title="Response per niche" icon={Target} rows={nicheRows} href={nichesIndex()} />
                    <RateList title="Response per mailbox" icon={MailboxIcon} rows={mailboxRows} href={mailboxesIndex()} />
                </div>

                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Radar className="size-3.5" />
                    Response is counted on leads: one reply per lead, however many steps it took.
                </p>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Home',
            href: dashboard(),
            icon: House,
        },
    ],
};
