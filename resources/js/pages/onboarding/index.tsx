import { Head, useForm, usePage, router } from '@inertiajs/react';
import { ArrowRight, Check, KeyRound, Mailbox as MailboxIcon, Plus } from 'lucide-react';
import type { SubmitEvent } from 'react';
import AppLogo from '@/components/app-logo';
import InputError from '@/components/input-error';
import { MailboxDialog } from '@/components/mailbox-dialog';
import { MailboxStatusBadge } from '@/components/mailbox-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { finish, key as storeKey } from '@/routes/onboarding';
import type { Mailbox } from '@/types';

type PageProps = {
    hasKey: boolean;
    mailboxes: Pick<Mailbox, 'id' | 'address' | 'status' | 'daily_limit' | 'connection_error'>[];
};

/**
 * Two things before the app can do anything: the Places key that feeds the
 * scraper, and a mailbox to send from. Both steps stay visible so it is clear what is left.
 */
export default function Onboarding() {
    const { hasKey, mailboxes } = usePage<PageProps>().props;
    const form = useForm({ google_places_key: '' });

    const saveKey = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeKey.url(), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const done = hasKey && mailboxes.length > 0;

    return (
        <div className="flex min-h-svh flex-col items-center px-4 py-12">
            <Head title="Set up" />

            <div className="mb-8 flex items-center gap-2">
                <AppLogo />
            </div>

            <div className="flex w-full max-w-lg flex-col gap-4">
                <div className="mb-2">
                    <h1 className="text-xl font-semibold tracking-tight">Two things before you start</h1>
                    <p className="text-sm text-muted-foreground">
                        The scraper needs a Google key, the sender needs a mailbox. That is all.
                    </p>
                </div>

                <Step number={1} title="Google Places API key" done={hasKey} icon={KeyRound}>
                    {hasKey ? (
                        <p className="text-sm text-muted-foreground">
                            Saved and encrypted. Change it later under Settings → Google.
                        </p>
                    ) : (
                        <form onSubmit={saveKey} className="flex flex-col gap-3">
                            <p className="text-sm text-muted-foreground">
                                In Google Cloud Console: enable <span className="text-foreground">Places API (New)</span>,
                                turn on billing (the first 1,000 searches a month are free), then Credentials → API key.
                            </p>
                            <div className="flex gap-2">
                                <Input
                                    value={form.data.google_places_key}
                                    onChange={(event) => form.setData('google_places_key', event.target.value)}
                                    placeholder="AIza…"
                                    className="font-mono text-xs"
                                    autoFocus
                                />
                                <Button type="submit" disabled={form.processing || form.data.google_places_key.trim() === ''}>
                                    Save
                                </Button>
                            </div>
                            <InputError message={form.errors.google_places_key} />
                        </form>
                    )}
                </Step>

                <Step number={2} title="A mailbox to send from" done={mailboxes.length > 0} icon={MailboxIcon}>
                    <div className="flex flex-col gap-3">
                        {mailboxes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Gmail with an app password works out of the box. Add more later to spread the load.
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y divide-border rounded-md border border-border">
                                {mailboxes.map((mailbox) => (
                                    <li key={mailbox.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                                        <span className="min-w-0 flex-1 truncate">{mailbox.address}</span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {mailbox.daily_limit} a day
                                        </span>
                                        <MailboxStatusBadge status={mailbox.status} />
                                    </li>
                                ))}
                            </ul>
                        )}
                        <MailboxDialog
                            onSaved={() => router.reload({ only: ['mailboxes'] })}
                            trigger={
                                <Button type="button" variant="outline" size="sm" className="self-start">
                                    <Plus />
                                    {mailboxes.length === 0 ? 'Add mailbox' : 'Add another'}
                                </Button>
                            }
                        />
                    </div>
                </Step>

                <Button
                    type="button"
                    size="lg"
                    className="mt-2 self-end"
                    disabled={!done}
                    onClick={() => router.post(finish.url())}
                >
                    Open the app
                    <ArrowRight />
                </Button>
            </div>
        </div>
    );
}

function Step({
    number,
    title,
    done,
    icon: Icon,
    children,
}: {
    number: number;
    title: string;
    done: boolean;
    icon: typeof KeyRound;
    children: React.ReactNode;
}) {
    return (
        <section
            className={cn(
                'flex flex-col gap-3 rounded-lg border p-4',
                done
                    ? 'border-border'
                    : 'border-(--raised-border) bg-accent/40 shadow-(--raised-shadow)',
            )}
        >
            <header className="flex items-center gap-3">
                <span
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        done
                            ? 'bg-linear-to-br from-sky-400 to-violet-500 text-white'
                            : 'bg-accent text-muted-foreground',
                    )}
                >
                    {done ? <Check className="size-3.5" /> : number}
                </span>
                <Icon className="size-4 shrink-0 text-muted-foreground" />
                <h2 className="text-sm font-medium">{title}</h2>
            </header>
            {children}
        </section>
    );
}
