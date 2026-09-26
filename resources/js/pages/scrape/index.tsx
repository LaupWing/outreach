import { Head } from '@inertiajs/react';
import { Plus, Radar } from 'lucide-react';
import { useState } from 'react';
import { PlacesUsageCard } from '@/components/places-usage';
import { ScrapeRunPanel } from '@/components/scrape-run-panel';
import { ScrapeRunsTable } from '@/components/scrape-runs-table';
import { Button } from '@/components/ui/button';
import { mockLeads } from '@/mock/leads';
import { mockNiches } from '@/mock/niches';
import { mockPlacesUsage, mockScrapeRuns } from '@/mock/scrape-runs';
import { index as scrapeIndex } from '@/routes/scrape';
import type { ScrapeRun } from '@/types';

export default function ScrapeIndex() {
    const [selected, setSelected] = useState<ScrapeRun | null>(null);
    // Keeps the last run while the panel slides shut.
    const [panelRun, setPanelRun] = useState<ScrapeRun | null>(null);

    return (
        <>
            <Head title="Scrape" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    {/* The budget sits above the runs: you check it before you start one. */}
                    <div className="shrink-0 border-b border-border p-4">
                        <PlacesUsageCard usage={mockPlacesUsage} />
                    </div>

                    <ScrapeRunsTable
                        runs={mockScrapeRuns}
                        niches={mockNiches}
                        selectedId={selected?.id ?? null}
                        onSelect={(run) => {
                            setSelected(run);
                            setPanelRun(run);
                        }}
                    />
                </div>
                <ScrapeRunPanel
                    run={panelRun}
                    niche={mockNiches.find((niche) => niche.id === panelRun?.niche_id)}
                    leads={mockLeads.filter((lead) => lead.scrape_run_id === panelRun?.id)}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

ScrapeIndex.layout = {
    breadcrumbs: [{ title: 'Scrape', href: scrapeIndex(), icon: Radar }],
    actions: (
        <Button variant="ghost" size="sm" className="text-muted-foreground">
            <Plus />
            New scrape
        </Button>
    ),
};
