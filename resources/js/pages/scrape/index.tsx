import { Head, usePage } from '@inertiajs/react';
import { Radar } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NewScrapeDialog } from '@/components/new-scrape-dialog';
import { PlacesUsageCard } from '@/components/places-usage';
import { ScrapeRunPanel } from '@/components/scrape-run-panel';
import { ScrapeRunsTable } from '@/components/scrape-runs-table';
import { mockLeads } from '@/mock/leads';
import { mockNiches } from '@/mock/niches';
import { mockPlacesUsage, mockScrapeRuns } from '@/mock/scrape-runs';
import { index as scrapeIndex } from '@/routes/scrape';
import type { ScrapeRun } from '@/types';

export default function ScrapeIndex() {
    // Search deep-links here with ?run=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('run');
    const linked = mockScrapeRuns.find((item) => String(item.id) === linkedId) ?? null;

    const [selected, setSelected] = useState<ScrapeRun | null>(linked);

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelected(linked);
            setSelected(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps
    // Keeps the last run while the panel slides shut.
    const [panelRun, setPanelRun] = useState<ScrapeRun | null>(linked);

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
    actions: <NewScrapeDialog niches={mockNiches} usage={mockPlacesUsage} />,
};
