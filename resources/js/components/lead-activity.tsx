import {
    AlertTriangle,
    CheckCircle2,
    Mail,
    Radar,
    Reply,
    Search,
    StickyNote,
    XCircle,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Lead, Message } from '@/types';

/** A note typed on the lead, as the `notes` relation serializes it. */
export type LeadNote = {
    id: number;
    lead_id: number;
    body: string;
    created_at: string;
};

type Event = {
    at: string;
    icon: typeof Mail;
    className: string;
    title: string;
    detail?: string;
};

const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

/**
 * There is no activity table in the briefing; the timeline is read off the lead
 * and its messages. If this earns its place, it becomes a table of its own.
 */
function eventsFor(lead: Lead, messages: Message[], notes: LeadNote[]): Event[] {
    const events: Event[] = [
        {
            at: lead.created_at,
            icon: Search,
            className: 'text-muted-foreground',
            title: `Found via ${lead.source === 'places' ? 'Google Places' : lead.source}`,
            detail: lead.city ?? undefined,
        },
    ];

    if (lead.signals) {
        events.push({
            at: lead.created_at,
            icon: Radar,
            className: lead.signals.blocked
                ? 'text-amber-600 dark:text-amber-400'
                : 'text-muted-foreground',
            title: lead.signals.blocked
                ? 'Enrichment blocked by the site'
                : lead.email
                  ? 'Enriched, email found'
                  : 'Enriched, no email found',
            detail: lead.signals.software.join(', ') || undefined,
        });
    }

    for (const message of messages) {
        if (message.sent_at) {
            events.push({
                at: message.sent_at,
                icon: message.status === 'bounced' ? AlertTriangle : Mail,
                className:
                    message.status === 'bounced'
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-sky-600 dark:text-sky-400',
                title:
                    message.status === 'bounced'
                        ? `Step ${message.step} bounced`
                        : `Step ${message.step} sent`,
                detail: message.subject,
            });
        }

        if (message.reply) {
            events.push({
                at: message.reply.received_at,
                icon: Reply,
                className: 'text-amber-600 dark:text-amber-400',
                title: 'Replied',
                detail: message.reply.body,
            });
        }
    }

    if (lead.status === 'customer' && lead.last_contact_at) {
        events.push({
            at: lead.last_contact_at,
            icon: CheckCircle2,
            className: 'text-green-600 dark:text-green-400',
            title: 'Became a customer',
        });
    }

    if (lead.status === 'no' && lead.last_contact_at) {
        events.push({
            at: lead.last_contact_at,
            icon: XCircle,
            className: 'text-muted-foreground',
            title: 'Said no',
        });
    }

    for (const note of notes) {
        events.push({
            at: note.created_at,
            icon: StickyNote,
            className: 'text-violet-600 dark:text-violet-400',
            title: 'Note',
            detail: note.body,
        });
    }

    // Newest on top.
    return events.sort((a, b) => b.at.localeCompare(a.at));
}

export function LeadActivity({
    lead,
    messages,
    notes = [],
}: {
    lead: Lead;
    messages: Message[];
    notes?: LeadNote[];
}) {
    const events = eventsFor(lead, messages, notes);

    return (
        <ol className="flex flex-col p-5">
            {events.map((event, index) => (
                <li key={index} className="relative flex gap-3 pb-5 last:pb-0">
                    {/* The line between the dots. */}
                    {index < events.length - 1 && (
                        <span className="absolute top-6 bottom-0 left-[9px] w-px bg-border" />
                    )}
                    <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-background">
                        <event.icon className={cn('size-4', event.className)} />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-baseline justify-between gap-3">
                            <span className="text-sm">{event.title}</span>
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {dateTime.format(new Date(event.at))}
                            </span>
                        </div>
                        {event.detail && (
                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                {event.detail}
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}
