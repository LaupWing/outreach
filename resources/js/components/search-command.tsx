import { router } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import {
    House,
    Inbox,
    Mail,
    Mailbox,
    MessageSquare,
    Radar,
    Search,
    Tag,
    Target,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { LeadStatusBadge } from '@/components/lead-status-badge';
import {
    Command,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { setSearchOpen, useSearchOpen } from '@/hooks/use-search';
import { dashboard, search } from '@/routes';
import { index as inboxIndex } from '@/routes/inbox';
import { index as leadsIndex } from '@/routes/leads';
import { index as mailboxesIndex } from '@/routes/mailboxes';
import { index as messagesIndex } from '@/routes/messages';
import { index as nichesIndex } from '@/routes/niches';
import { index as offersIndex } from '@/routes/offers';
import { index as scrapeIndex } from '@/routes/scrape';
import type { LeadStatus } from '@/types';

const pages = [
    { title: 'Home', icon: House, url: dashboard().url },
    { title: 'Inbox', icon: Inbox, url: inboxIndex().url },
    { title: 'Scrape', icon: Radar, url: scrapeIndex().url },
    { title: 'Leads', icon: Users, url: leadsIndex().url },
    { title: 'Niches', icon: Target, url: nichesIndex().url },
    { title: 'Offers', icon: Tag, url: offersIndex().url },
    { title: 'Mailboxes', icon: Mailbox, url: mailboxesIndex().url },
    { title: 'Messages', icon: MessageSquare, url: messagesIndex().url },
];

/** What the search endpoint returns, five per group. */
type Results = {
    leads: { id: number; company: string; city: string | null; status: LeadStatus }[];
    niches: { id: number; name: string }[];
    offers: { id: number; name: string; niche: { name: string } | null }[];
    mailboxes: { id: number; address: string }[];
    messages: { id: number; subject: string; lead: { company: string } | null }[];
    runs: { id: number; query: string; place: string }[];
};

const empty: Results = { leads: [], niches: [], offers: [], mailboxes: [], messages: [], runs: [] };

/** Every word of the query has to appear somewhere in the haystack. */
function matches(query: string, haystack: string): boolean {
    const text = haystack.toLowerCase();

    return query
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean)
        .every((word) => text.includes(word));
}

/**
 * Spotlight-style search over everything, opened with ⌘K or the sidebar item.
 * Searches the mock data for now; with real data this becomes one search endpoint.
 */
export function SearchCommand() {
    const open = useSearchOpen();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Results>(empty);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                setSearchOpen(!open);
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open]);

    // Start clean every time it opens.
    useEffect(() => {
        if (open) {
            setQuery('');
            setResults(empty);
        }
    }, [open]);

    // Ask the server after a short pause in typing; a stale answer is dropped.
    useEffect(() => {
        if (query.trim() === '') {
            setResults(empty);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(search.url({ query: { q: query.trim() } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : empty))
                .then((data: Results) => setResults(data))
                .catch(() => undefined);
        }, 150);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const go = (url: string) => {
        setSearchOpen(false);
        router.visit(url);
    };

    const typing = query.trim() !== '';
    const foundPages = typing
        ? pages.filter((page) => matches(query, page.title))
        : pages;
    const { leads: foundLeads, niches: foundNiches, offers: foundOffers, mailboxes: foundMailboxes, messages: foundMessages, runs: foundRuns } = results;

    const nothing =
        typing &&
        foundPages.length === 0 &&
        Object.values(results).every((group) => group.length === 0);

    return (
        <DialogPrimitive.Root open={open} onOpenChange={setSearchOpen}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    className="fixed top-[18vh] left-1/2 z-50 w-full max-w-xl -translate-x-1/2 overflow-hidden rounded-xl border border-(--raised-border) bg-background shadow-2xl shadow-black/40 outline-hidden data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
                >
                    <DialogPrimitive.Title className="sr-only">
                        Search
                    </DialogPrimitive.Title>
                    <Command loop shouldFilter={false}>
                        <div className="flex items-center gap-3 border-b border-border px-4">
                            <Search className="size-5 shrink-0 text-muted-foreground" />
                            <CommandInput
                                value={query}
                                onValueChange={setQuery}
                                placeholder="Search leads, niches, offers, mailboxes, messages…"
                                className="h-14 text-base"
                                wrapperClassName="h-14 flex-1 border-0 px-0"
                                hideIcon
                            />
                            <kbd className="hidden shrink-0 rounded-md border border-border bg-accent px-1.5 py-0.5 font-sans text-[10px] text-muted-foreground sm:block">
                                esc
                            </kbd>
                        </div>
                        <CommandList className="max-h-[50vh] p-2">
                            {nothing && (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Nothing found for "{query}".
                                </p>
                            )}

                            {foundPages.length > 0 && (
                            <CommandGroup heading="Pages">
                                {foundPages.map((page) => (
                                    <CommandItem
                                        key={page.url}
                                        value={`page ${page.title}`}
                                        onSelect={() => go(page.url)}
                                    >
                                        <page.icon />
                                        {page.title}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            )}

                            {foundLeads.length > 0 && (
                            <CommandGroup heading="Leads">
                                {foundLeads.map((lead) => (
                                    <CommandItem
                                        key={lead.id}
                                        value={`lead ${lead.id}`}
                                        onSelect={() =>
                                            go(leadsIndex({ query: { lead: lead.id } }).url)
                                        }
                                    >
                                        <CompanyAvatar name={lead.company} className="size-5 text-[9px]" />
                                        <span className="min-w-0 flex-1 truncate">
                                            {lead.company}
                                            {lead.city && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {lead.city}
                                                </span>
                                            )}
                                        </span>
                                        <LeadStatusBadge status={lead.status} className="shrink-0" />
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            )}

                            {foundNiches.length > 0 && (
                            <CommandGroup heading="Niches">
                                {foundNiches.map((niche) => (
                                    <CommandItem
                                        key={niche.id}
                                        value={`niche ${niche.id}`}
                                        onSelect={() =>
                                            go(nichesIndex({ query: { niche: niche.id } }).url)
                                        }
                                    >
                                        <Target />
                                        {niche.name}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            )}

                            {foundOffers.length > 0 && (
                            <CommandGroup heading="Offers">
                                {foundOffers.map((offer) => (
                                    <CommandItem
                                        key={offer.id}
                                        value={`offer ${offer.id}`}
                                        onSelect={() =>
                                            go(offersIndex({ query: { offer: offer.id } }).url)
                                        }
                                    >
                                        <Tag />
                                        <span className="min-w-0 flex-1 truncate">
                                            {offer.name}
                                            {offer.niche && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {offer.niche.name}
                                                </span>
                                            )}
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            )}

                            {foundMailboxes.length > 0 && (
                            <CommandGroup heading="Mailboxes">
                                {foundMailboxes.map((mailbox) => (
                                    <CommandItem
                                        key={mailbox.id}
                                        value={`mailbox ${mailbox.id}`}
                                        onSelect={() =>
                                            go(mailboxesIndex({ query: { mailbox: mailbox.id } }).url)
                                        }
                                    >
                                        <Mailbox />
                                        {mailbox.address}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            )}

                            {foundMessages.length > 0 && (
                            <CommandGroup heading="Messages">
                                {foundMessages.map((message) => (
                                    <CommandItem
                                        key={message.id}
                                        value={`message ${message.id}`}
                                        onSelect={() =>
                                            go(messagesIndex({ query: { message: message.id } }).url)
                                        }
                                    >
                                        <Mail />
                                        <span className="min-w-0 flex-1 truncate">
                                            {message.subject}
                                            {message.lead && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {message.lead.company}
                                                </span>
                                            )}
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            )}

                            {foundRuns.length > 0 && (
                            <CommandGroup heading="Scrapes">
                                {foundRuns.map((run) => (
                                    <CommandItem
                                        key={run.id}
                                        value={`scrape ${run.id}`}
                                        onSelect={() =>
                                            go(scrapeIndex({ query: { run: run.id } }).url)
                                        }
                                    >
                                        <Radar />
                                        <span className="min-w-0 flex-1 truncate">
                                            {run.query}
                                            <span className="text-muted-foreground">
                                                {' '}
                                                in {run.place}
                                            </span>
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            )}
                        </CommandList>
                    </Command>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
