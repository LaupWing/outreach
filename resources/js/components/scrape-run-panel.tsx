import { Link } from '@inertiajs/react';
import {
    Calendar,
    Coins,
    ExternalLink,
    FileText,
    Mail,
    MapPin,
    Search,
    ShieldAlert,
    Target,
    Timer,
    Users,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadStatusBadge } from '@/components/lead-status-badge';
import {
    SidePanel,
    SidePanelEmpty,
    SidePanelHeader,
    SidePanelRow,
    SidePanelStat,
    SidePanelTabs,
} from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as leadsIndex } from '@/routes/leads';
import type { Lead, Niche, ScrapeRun } from '@/types';

const tabs = [
    { key: 'overview', label: 'Overview', icon: FileText },
    { key: 'leads', label: 'Leads', icon: Users },
] as const;

type Tab = (typeof tabs)[number]['key'];

const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

function duration(run: ScrapeRun): string | null {
    if (!run.finished_at) {
        return null;
    }

    const minutes = Math.round(
        (new Date(run.finished_at).getTime() -
            new Date(run.started_at).getTime()) /
            60_000,
    );

    return minutes < 1 ? 'under a minute' : `${minutes} min`;
}

/** The run card: what it cost, what it found and the leads that came out of it. */
export function ScrapeRunPanel({
    run,
    niche,
    leads,
    open,
    onClose,
}: {
    run: ScrapeRun | null;
    niche: Niche | undefined;
    leads: Lead[];
    open: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<Tab>('overview');
    const [blockedOnly, setBlockedOnly] = useState(false);

    // Sites the enricher could not read: blocked us, or render fully with JavaScript.
    const isFlagged = (lead: Lead) =>
        Boolean(lead.signals?.blocked || lead.signals?.javascript_only);
    const flagged = leads.filter(isFlagged);
    const shown = blockedOnly ? flagged : leads;

    return (
        <SidePanel open={open}>
            {run && (
                <>
                    <SidePanelHeader
                        parent="Scrape"
                        title={`${run.query} in ${run.place}`}
                        onClose={onClose}
                    />

                    <div className="flex flex-col gap-4 px-5 pt-5 pb-4">
                        <div className="flex items-center gap-3">
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-sky-400 to-violet-500 text-white">
                                <Search className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <h2 className="truncate text-lg font-semibold tracking-tight">
                                    {run.query}
                                </h2>
                                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <MapPin className="size-3.5" />
                                    {run.place}
                                </span>
                            </div>
                        </div>

                        {/* The three numbers you look at first. */}
                        <div className="grid grid-cols-3 gap-2">
                            <SidePanelStat label="Found" value={run.found} />
                            <SidePanelStat
                                label="With email"
                                value={run.with_email}
                                hint={
                                    run.found > 0
                                        ? `${Math.round((run.with_email / run.found) * 100)}%`
                                        : undefined
                                }
                            />
                            <SidePanelStat
                                label="Blocked"
                                value={run.blocked}
                                warn={run.blocked > 0}
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={leadsIndex()}
                                    prefetch
                                >
                                    <Users />
                                    Open in leads
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm">
                                <Search />
                                Run again
                            </Button>
                        </div>
                    </div>

                    <SidePanelTabs
                        tabs={tabs.map((item) => ({
                            ...item,
                            badge: item.key === 'leads' ? leads.length : undefined,
                        }))}
                        value={tab}
                        onChange={setTab}
                    />

                    <div className="min-h-0 flex-1 overflow-auto">
                        {tab === 'overview' && (
                            <dl className="flex flex-col py-2">
                                <SidePanelRow icon={Target} label="Niche">
                                    {niche?.name ?? <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={Coins} label="Requests">
                                    {run.requests}
                                    <span className="text-muted-foreground">
                                        {' '}
                                        of the free 1,000 this month
                                    </span>
                                </SidePanelRow>
                                <SidePanelRow icon={Calendar} label="Started">
                                    {dateTime.format(new Date(run.started_at))}
                                </SidePanelRow>
                                <SidePanelRow icon={Timer} label="Duration">
                                    {duration(run) ?? (
                                        <SidePanelEmpty>
                                            Still running
                                        </SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                                <SidePanelRow icon={Mail} label="Email rate">
                                    {run.found > 0
                                        ? `${run.with_email} of ${run.found}`
                                        : <SidePanelEmpty />}
                                </SidePanelRow>
                                <SidePanelRow icon={ShieldAlert} label="Blocked">
                                    {run.blocked > 0 ? (
                                        `${run.blocked} sites flagged, kept for a manual look`
                                    ) : (
                                        <SidePanelEmpty>None</SidePanelEmpty>
                                    )}
                                </SidePanelRow>
                            </dl>
                        )}

                        {tab === 'leads' &&
                            (leads.length === 0 ? (
                                <p className="p-5 text-sm text-muted-foreground">
                                    {run.status === 'running'
                                        ? 'Leads show up here as the run finishes.'
                                        : 'No leads from this run.'}
                                </p>
                            ) : (
                                <>
                                    {/* Blocked sites need a look by hand, so they get their own switch. */}
                                    <div className="flex items-center gap-1 border-b border-border px-4 py-2">
                                        <Toggle
                                            active={!blockedOnly}
                                            onClick={() => setBlockedOnly(false)}
                                        >
                                            All ({leads.length})
                                        </Toggle>
                                        <Toggle
                                            active={blockedOnly}
                                            onClick={() => setBlockedOnly(true)}
                                            warn
                                        >
                                            Blocked ({flagged.length})
                                        </Toggle>
                                    </div>
                                    <ul className="flex flex-col">
                                        {shown.map((lead) => (
                                            <li
                                                key={lead.id}
                                                className="flex items-center gap-3 border-b border-border px-5 py-2.5 text-sm"
                                            >
                                                <CompanyAvatar name={lead.company} />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-medium">
                                                        {lead.company}
                                                    </span>
                                                    {isFlagged(lead) ? (
                                                        <span className="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                                            <ShieldAlert className="size-3" />
                                                            {lead.signals?.blocked
                                                                ? 'Blocked the scraper'
                                                                : 'JavaScript only'}
                                                        </span>
                                                    ) : (
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {lead.email ?? 'No email found'}
                                                        </span>
                                                    )}
                                                </span>
                                                {isFlagged(lead) && lead.website ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="shrink-0"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`https://${lead.website}`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <ExternalLink />
                                                            Check site
                                                        </a>
                                                    </Button>
                                                ) : (
                                                    <LeadStatusBadge
                                                        status={lead.status}
                                                        className="shrink-0"
                                                    />
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            ))}
                    </div>
                </>
            )}
        </SidePanel>
    );
}

function Toggle({
    active,
    warn = false,
    onClick,
    children,
}: {
    active: boolean;
    warn?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-md border px-2 py-1 text-xs transition-colors',
                active
                    ? 'border-border bg-accent text-foreground'
                    : 'border-transparent text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                warn && active && 'text-amber-600 dark:text-amber-400',
            )}
        >
            {children}
        </button>
    );
}
