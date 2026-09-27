import { router, usePage } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { destroy as disconnectClaude } from '@/routes/claude';

type Client = 'code' | 'web';

const clients: { id: Client; label: string }[] = [
    { id: 'code', label: 'Claude Code' },
    { id: 'web', label: 'claude.ai' },
];

const shortDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
});

/** How long the button reads "Copied" after a click. */
const COPIED_MS = 2000;

function CopyButton({
    text,
    label = 'Copy',
}: {
    text: string;
    label?: string;
}) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), COPIED_MS);

        return () => window.clearTimeout(timer);
    }, [copied]);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
        } catch (error) {
            console.warn('Copy failed', error);
        }
    };

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={copy}
            className="shrink-0"
        >
            {copied ? <Check /> : <Copy />}
            {copied ? 'Copied' : label}
        </Button>
    );
}

/** A read-only value in mono with a copy button beside it: the URL, the command. */
function CopyField({ value, label }: { value: string; label: string }) {
    return (
        <div className="flex min-w-0 items-center gap-2">
            {/* Inline code cannot truncate; block it so a long URL stays inside the dialog. */}
            <code
                aria-label={label}
                className="block min-w-0 flex-1 truncate rounded-md border border-border bg-accent/40 px-3 py-1.5 font-mono text-xs"
            >
                {value}
            </code>
            <CopyButton text={value} />
        </div>
    );
}

/**
 * Connect Claude Code or claude.ai to this account over the hosted MCP endpoint.
 * Both log in here through OAuth; the list below shows which clients hold a token.
 */
export function ConnectClaude({ compact = false }: { compact?: boolean }) {
    const { auth } = usePage().props;
    const { url, connections } = auth.claude;
    const [client, setClient] = useState<Client>('code');

    const command = `claude mcp add --transport http snelreach ${url}`;

    return (
        <div className={cn('grid min-w-0', compact ? 'gap-3' : 'gap-5')}>
            <div className="grid min-w-0 gap-1.5">
                <span className="text-xs font-medium text-muted-foreground">
                    MCP endpoint
                </span>
                <CopyField value={url} label="MCP endpoint" />
            </div>

            <div className="grid min-w-0 gap-3">
                {/* Segmented control: a sunken track, the chosen client raised like the active sidebar item. */}
                <div
                    role="radiogroup"
                    aria-label="Client"
                    className="flex rounded-md border border-border bg-accent/40 p-0.5"
                >
                    {clients.map(({ id, label }) => (
                        <button
                            key={id}
                            type="button"
                            role="radio"
                            aria-checked={client === id}
                            onClick={() => setClient(id)}
                            className={cn(
                                'flex-1 rounded-[5px] border px-2 py-1.5 text-sm transition-colors',
                                client === id
                                    ? 'border-(--raised-border) bg-background text-foreground shadow-(--raised-shadow)'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {client === 'code' ? (
                    <div className="grid min-w-0 gap-2">
                        <CopyField
                            value={command}
                            label="Claude Code command"
                        />
                        <p className="text-sm text-muted-foreground">
                            Then run{' '}
                            <code className="font-mono text-xs text-foreground">
                                claude
                            </code>
                            , type{' '}
                            <code className="font-mono text-xs text-foreground">
                                /mcp
                            </code>
                            , pick snelreach and Authenticate. You log in here
                            once and click Allow.
                        </p>
                    </div>
                ) : (
                    <ol className="grid list-decimal gap-1 pl-5 text-sm text-muted-foreground marker:text-foreground">
                        <li>Settings → Connectors → Add custom connector</li>
                        <li>
                            Name:{' '}
                            <span className="text-foreground">Snelreach</span>,
                            URL:{' '}
                            <code className="font-mono text-xs text-foreground">
                                {url}
                            </code>
                        </li>
                        <li>Click Add, log in here, click Allow.</li>
                    </ol>
                )}
            </div>

            <div className="grid gap-2">
                {(!compact || connections.length > 0) && (
                    <span className="text-xs font-medium text-muted-foreground">
                        Connected
                    </span>
                )}
                {connections.length === 0 ? (
                    !compact && (
                        <p className="text-sm text-muted-foreground">
                            Nothing connected yet.
                        </p>
                    )
                ) : (
                    <ul className="flex flex-col divide-y divide-border rounded-md border border-border">
                        {connections.map((connection) => (
                            <li
                                key={connection.client_id}
                                className="flex items-center gap-3 px-3 py-2 text-sm"
                            >
                                <span className="flex min-w-0 flex-1 flex-col">
                                    <span className="truncate">
                                        {connection.name}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {connection.connected_at
                                            ? `since ${shortDate.format(new Date(connection.connected_at))}`
                                            : 'since an unknown date'}
                                        {' · '}
                                        {connection.last_used_at
                                            ? `last used ${shortDate.format(new Date(connection.last_used_at))}`
                                            : 'not used yet'}
                                    </span>
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            disconnectClaude.url(
                                                connection.client_id,
                                            ),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Disconnect
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                Working locally? The stdio server in .mcp.json keeps working
                without OAuth.
            </p>
        </div>
    );
}
