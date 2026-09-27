import { router } from '@inertiajs/react';
import {
    AtSign,
    Check,
    Flame,
    Gauge,
    KeyRound,
    Mail,
    Plus,
    Server,
    User,
} from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/mailboxes';
import type { Mailbox } from '@/types';

/** Gmail and Workspace share these; other providers get typed in by hand. */
const GMAIL = {
    imap_host: 'imap.gmail.com',
    imap_port: 993,
    smtp_host: 'smtp.gmail.com',
    smtp_port: 587,
};

const valuesFrom = (mailbox?: Mailbox) => ({
    address: mailbox?.address ?? '',
    imapHost: mailbox?.imap_host ?? GMAIL.imap_host,
    imapPort: mailbox?.imap_port ?? GMAIL.imap_port,
    smtpHost: mailbox?.smtp_host ?? GMAIL.smtp_host,
    smtpPort: mailbox?.smtp_port ?? GMAIL.smtp_port,
    username: mailbox?.username ?? '',
    dailyLimit: mailbox?.daily_limit ?? 20,
    warmUp: mailbox ? mailbox.status === 'warming_up' : true,
});

/**
 * Adds a sending mailbox over IMAP/SMTP, or edits one when `mailbox` is given.
 * Gmail works with an app password (2-step verification on, then
 * myaccount.google.com/apppasswords); no Google Cloud project needed.
 */
export function MailboxDialog({
    mailbox,
    trigger,
    onSaved,
}: {
    mailbox?: Mailbox;
    /** Replaces the default "+ Mailbox" button. */
    trigger?: ReactNode;
    onSaved?: () => void;
}) {
    const editing = mailbox !== undefined;
    const initial = valuesFrom(mailbox);

    const [open, setOpen] = useState(false);
    const [address, setAddress] = useState(initial.address);
    const [imapHost, setImapHost] = useState(initial.imapHost);
    const [imapPort, setImapPort] = useState(initial.imapPort);
    const [smtpHost, setSmtpHost] = useState(initial.smtpHost);
    const [smtpPort, setSmtpPort] = useState(initial.smtpPort);
    const [username, setUsername] = useState(initial.username);
    const [password, setPassword] = useState('');
    const [dailyLimit, setDailyLimit] = useState(initial.dailyLimit);
    const [warmUp, setWarmUp] = useState(initial.warmUp);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const isGmail =
        imapHost === GMAIL.imap_host && smtpHost === GMAIL.smtp_host;

    // Editing keeps the stored password unless a new one is typed.
    const ready =
        address.trim() !== '' &&
        imapHost.trim() !== '' &&
        smtpHost.trim() !== '' &&
        (editing || password.trim() !== '');

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        const data = {
            address,
            imap_host: imapHost,
            imap_port: imapPort,
            smtp_host: smtpHost,
            smtp_port: smtpPort,
            username: username || null,
            password: password || null,
            daily_limit: dailyLimit,
            warm_up: warmUp,
        };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (bag: Record<string, string>) => setErrors(bag),
            onSuccess: () => {
                setOpen(false);
                onSaved?.();
            },
        };

        if (editing) {
            const { address: _address, ...rest } = data;

            router.patch(update.url(mailbox.id), rest, options);

            return;
        }

        router.post(store.url(), data, options);
    };

    const toggle = (value: boolean) => {
        setOpen(value);
        setErrors({});

        if (value) {
            const values = valuesFrom(mailbox);
            setAddress(values.address);
            setImapHost(values.imapHost);
            setImapPort(values.imapPort);
            setSmtpHost(values.smtpHost);
            setSmtpPort(values.smtpPort);
            setUsername(values.username);
            setPassword('');
            setDailyLimit(values.dailyLimit);
            setWarmUp(values.warmUp);
        }
    };

    return (
        <Dialog open={open} onOpenChange={toggle}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground"
                    >
                        <Plus />
                        Mailbox
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-md">
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col gap-5"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit mailbox' : 'New mailbox'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Change the servers, password, limit or warm-up. Test the connection after saving.'
                                : 'An address to send from. Gmail works with an app password: turn on 2-step verification, then create one at myaccount.google.com/apppasswords.'}
                        </DialogDescription>
                    </DialogHeader>

                    {/* The fields scroll; header and footer stay put on a short screen. */}
                    <div className="-mx-1 grid min-h-0 flex-1 gap-4 overflow-y-auto px-1">
                        <Field
                            label="Address"
                            icon={AtSign}
                            htmlFor="mailbox-address"
                        >
                            <Input
                                id="mailbox-address"
                                type="email"
                                value={address}
                                onChange={(event) =>
                                    setAddress(event.target.value)
                                }
                                placeholder="loc@snelstack.com"
                                disabled={editing}
                                autoFocus={!editing}
                            />
                            <InputError message={errors.address} />
                        </Field>

                        {/* Provider presets: Gmail fills the four server fields; anything else is typed. */}
                        <div
                            className="flex gap-1 rounded-md border border-border bg-accent/40 p-0.5"
                            role="radiogroup"
                            aria-label="Provider"
                        >
                            <Preset
                                active={isGmail}
                                onClick={() => {
                                    setImapHost(GMAIL.imap_host);
                                    setImapPort(GMAIL.imap_port);
                                    setSmtpHost(GMAIL.smtp_host);
                                    setSmtpPort(GMAIL.smtp_port);
                                }}
                            >
                                <Mail className="size-3.5" />
                                Gmail
                            </Preset>
                            <Preset
                                active={!isGmail}
                                onClick={() => {
                                    setImapHost('');
                                    setSmtpHost('');
                                }}
                            >
                                <Server className="size-3.5" />
                                Other provider
                            </Preset>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-[1fr_5.5rem]">
                            <Field
                                label="IMAP host"
                                icon={Server}
                                htmlFor="mailbox-imap"
                            >
                                <Input
                                    id="mailbox-imap"
                                    value={imapHost}
                                    onChange={(event) =>
                                        setImapHost(event.target.value)
                                    }
                                    placeholder="imap.example.com"
                                />
                                <InputError message={errors.imap_host} />
                            </Field>
                            <Field label="Port" htmlFor="mailbox-imap-port">
                                <Input
                                    id="mailbox-imap-port"
                                    type="number"
                                    value={imapPort}
                                    onChange={(event) =>
                                        setImapPort(Number(event.target.value))
                                    }
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-[1fr_5.5rem]">
                            <Field
                                label="SMTP host"
                                icon={Server}
                                htmlFor="mailbox-smtp"
                            >
                                <Input
                                    id="mailbox-smtp"
                                    value={smtpHost}
                                    onChange={(event) =>
                                        setSmtpHost(event.target.value)
                                    }
                                    placeholder="smtp.example.com"
                                />
                                <InputError message={errors.smtp_host} />
                            </Field>
                            <Field label="Port" htmlFor="mailbox-smtp-port">
                                <Input
                                    id="mailbox-smtp-port"
                                    type="number"
                                    value={smtpPort}
                                    onChange={(event) =>
                                        setSmtpPort(Number(event.target.value))
                                    }
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Username"
                                icon={User}
                                htmlFor="mailbox-username"
                            >
                                <Input
                                    id="mailbox-username"
                                    value={username}
                                    onChange={(event) =>
                                        setUsername(event.target.value)
                                    }
                                    placeholder="Same as the address"
                                />
                            </Field>
                            <Field
                                label="App password"
                                icon={KeyRound}
                                htmlFor="mailbox-password"
                            >
                                <Input
                                    id="mailbox-password"
                                    type="password"
                                    value={password}
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                    placeholder={
                                        editing
                                            ? 'Leave empty to keep'
                                            : '16 characters'
                                    }
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} />
                            </Field>
                        </div>

                        <Field
                            label="Daily limit"
                            icon={Gauge}
                            htmlFor="mailbox-limit"
                        >
                            <Input
                                id="mailbox-limit"
                                type="number"
                                min={1}
                                max={200}
                                value={dailyLimit}
                                onChange={(event) =>
                                    setDailyLimit(Number(event.target.value))
                                }
                                className="w-32"
                            />
                            <InputError message={errors.daily_limit} />
                        </Field>

                        {/* A fresh address that sends 40 a day on day one lands in spam; the sender ramps the limit itself. */}
                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-3 shadow-(--raised-shadow)">
                            <input
                                type="checkbox"
                                checked={warmUp}
                                onChange={(event) =>
                                    setWarmUp(event.target.checked)
                                }
                                className="mt-0.5 size-4 shrink-0 accent-violet-500"
                            />
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="flex items-center gap-1.5 text-sm">
                                    <Flame className="size-4 shrink-0 text-muted-foreground" />
                                    Warm up
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Start at 5 a day and grow to {dailyLimit}{' '}
                                    over two weeks.
                                </span>
                            </span>
                        </label>
                    </div>

                    <DialogFooter>
                        <Button
                            key="cancel"
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            key="submit"
                            type="submit"
                            disabled={!ready || processing}
                        >
                            {editing ? <Check /> : <Plus />}
                            {editing ? 'Save' : 'Add mailbox'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Preset({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            onClick={onClick}
            className={cn(
                'flex flex-1 items-center justify-center gap-1.5 rounded-[5px] border px-2 py-1.5 text-xs transition-colors',
                active
                    ? 'border-(--raised-border) bg-background text-foreground shadow-(--raised-shadow)'
                    : 'border-transparent text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function Field({
    label,
    icon: Icon,
    htmlFor,
    children,
}: {
    label: string;
    icon?: typeof Mail;
    htmlFor?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label
                htmlFor={htmlFor}
                className="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                {Icon && <Icon className="size-3.5 shrink-0" />}
                {label}
            </Label>
            {children}
        </div>
    );
}
