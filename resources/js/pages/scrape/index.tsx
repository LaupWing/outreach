import { Head } from '@inertiajs/react';
import { Plus, Radar } from 'lucide-react';
import { PlacesUsageCard } from '@/components/places-usage';
import { ScrapeRunsTable } from '@/components/scrape-runs-table';
import { Button } from '@/components/ui/button';
import { mockNiches } from '@/mock/niches';
import { mockPlacesUsage, mockScrapeRuns } from '@/mock/scrape-runs';
import { index as scrapeIndex } from '@/routes/scrape';

export default function ScrapeIndex() {
    return (
        <>
            <Head title="Scrape" />

            {/* The budget sits above the runs: you check it before you start one. */}
            <div className="shrink-0 border-b border-border p-4">
                <PlacesUsageCard usage={mockPlacesUsage} />
            </div>

            <ScrapeRunsTable runs={mockScrapeRuns} niches={mockNiches} />
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
