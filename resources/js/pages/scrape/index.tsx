import { Head, usePage, usePoll } from '@inertiajs/react';
import { Radar } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NewScrapeDialog } from '@/components/new-scrape-dialog';
import { PlacesUsageCard } from '@/components/places-usage';
import { ScrapeRunPanel, type ScrapeLead } from '@/components/scrape-run-panel';
import { ScrapeRunsTable } from '@/components/scrape-runs-table';
import { index as scrapeIndex } from '@/routes/scrape';
import type { Niche, PlacesUsage, ScrapeRun } from '@/types';

type PageProps = {
    /** Newest first. */
    runs: ScrapeRun[];
    niches: Niche[];
    /** Only the leads a run found, lean: what the panel's list shows. */
    leads: ScrapeLead[];
    usage: PlacesUsage;
};

export default function ScrapeIndex() {
    const { runs, niches, leads, usage } = usePage<PageProps>().props;

    // While a run works on the queue, refresh the counts every few seconds; idle otherwise.
    const busy = runs.some((run) => run.status === 'queued' || run.status === 'running');
    const { start, stop } = usePoll(3000, { only: ['runs', 'leads', 'usage'] }, { autoStart: false });

    useEffect(() => {
        if (busy) {
            start();
        } else {
            stop();
        }
    }, [busy, start, stop]);

    // Search deep-links here with ?run=ID.
    const { url } = usePage();
    const linkedId = new URLSearchParams(url.split('?')[1] ?? '').get('run');
    const linked = runs.find((item) => String(item.id) === linkedId) ?? null;

    const [selected, setSelected] = useState<ScrapeRun | null>(linked);
    // Keeps the last run while the panel slides shut.
    const [panelRun, setPanelRun] = useState<ScrapeRun | null>(linked);

    // Same page, new ?id: the component stays mounted, so follow the link by hand.
    useEffect(() => {
        if (linked) {
            setSelected(linked);
            setPanelRun(linked);
        }
    }, [linkedId]); // eslint-disable-line react-hooks/exhaustive-deps

    // Fresh props after a mutation: keep showing the same run, with its new counts.
    useEffect(() => {
        setPanelRun((current) =>
            current
                ? (runs.find((item) => item.id === current.id) ?? current)
                : current,
        );
        setSelected((current) =>
            current
                ? (runs.find((item) => item.id === current.id) ?? null)
                : current,
        );
    }, [runs]);

    return (
        <>
            <Head title="Scrape" />

            <div className="flex min-h-0 flex-1">
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    {/* The budget sits above the runs: you check it before you start one. */}
                    <div className="shrink-0 border-b border-border p-4">
                        <PlacesUsageCard usage={usage} />
                    </div>

                    <ScrapeRunsTable
                        runs={runs}
                        niches={niches}
                        selectedId={selected?.id ?? null}
                        onSelect={(run) => {
                            setSelected(run);
                            setPanelRun(run);
                        }}
                    />
                </div>
                <ScrapeRunPanel
                    run={panelRun}
                    niche={niches.find((niche) => niche.id === panelRun?.niche_id)}
                    niches={niches}
                    usage={usage}
                    leads={leads.filter((lead) => lead.scrape_run_id === panelRun?.id)}
                    open={selected !== null}
                    onClose={() => setSelected(null)}
                />
            </div>
        </>
    );
}

/** The topbar action lives outside the page tree, so it reads the props itself. */
function NewScrapeAction() {
    const { niches, usage } = usePage<PageProps>().props;

    return <NewScrapeDialog niches={niches} usage={usage} />;
}

ScrapeIndex.layout = {
    breadcrumbs: [{ title: 'Scrape', href: scrapeIndex(), icon: Radar }],
    actions: <NewScrapeAction />,
};
