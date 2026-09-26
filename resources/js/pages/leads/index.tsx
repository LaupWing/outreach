import { Head } from '@inertiajs/react';
import { Plus, SlidersHorizontal, Users } from 'lucide-react';
import { useState } from 'react';
import { leadStatuses } from '@/components/lead-status-badge';
import { LeadsTable } from '@/components/leads-table';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { mockLeads } from '@/mock/leads';
import { mockNiches } from '@/mock/niches';
import { index as leadsIndex } from '@/routes/leads';
import type { Lead, LeadStatus } from '@/types';

export default function LeadsIndex() {
    const [status, setStatus] = useState<LeadStatus | null>(null);
    const [selected, setSelected] = useState<Lead | null>(null);

    const leads = status
        ? mockLeads.filter((lead) => lead.status === status)
        : mockLeads;

    return (
        <>
            <Head title="Leads" />

            {/* Filter bar, like the reference: a label and a row of chips. */}
            <div className="flex h-12 shrink-0 items-center gap-2 border-b border-border px-4">
                <span className="flex items-center gap-2 text-sm text-muted-foreground">
                    <SlidersHorizontal className="size-4" />
                    Filters:
                </span>
                <div className="flex items-center gap-1">
                    <FilterChip
                        active={status === null}
                        onClick={() => setStatus(null)}
                    >
                        All
                    </FilterChip>
                    {(Object.keys(leadStatuses) as LeadStatus[]).map((key) => (
                        <FilterChip
                            key={key}
                            active={status === key}
                            onClick={() => setStatus(key)}
                        >
                            {leadStatuses[key].label}
                        </FilterChip>
                    ))}
                </div>
            </div>

            <LeadsTable
                leads={leads}
                niches={mockNiches}
                selectedId={selected?.id ?? null}
                onSelect={setSelected}
            />
        </>
    );
}

function FilterChip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: string;
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
            )}
        >
            {children}
        </button>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: leadsIndex(), icon: Users }],
    actions: (
        <Button variant="ghost" size="sm" className="text-muted-foreground">
            <Plus />
            Lead
        </Button>
    ),
};
