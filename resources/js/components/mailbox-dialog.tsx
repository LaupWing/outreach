import { router } from '@inertiajs/react';
import {
    AtSign,
    Check,
    ChevronLeft,
    ChevronRight,
    Copy,
    Flame,
    Gauge,
    KeyRound,
    Mail,
    Plus,
    RefreshCw,
    Server,
} from 'lucide-react';
import { useState, type ReactNode, type SubmitEvent } from 'react';
import { DialogSteps } from '@/components/dialog-steps';
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
import type { Mailbox, MailboxType } from '@/types';

/** Pick the connection first, then set it up; the two forms look nothing alike. */
const STEPS = ['Connection', 'Set up'];

/** Where the mock OAuth link points; Laravel will mint a real one with a state token. */
const MOCK_CONNECT_URL =
    'https://accounts.google.com/o/oauth2/v2/auth?prompt=select_account&state=mock';

const types: {
    value: MailboxType;
    label: string;
    description: string;
    icon: typeof Mail;
}[] = [
    {
        value: 'gmail',
        label: 'Gmail',
        description:
            'Google Workspace or a plain Gmail address. Connects through Google, no password stored here.',
        icon: Mail,
    },
    {
        value: 'imap',
        label: 'IMAP / SMTP',
        description:
            'Any other provider. Needs the server addresses and an app password.',
        icon: Server,
    },
];

/** Form values for a mailbox, or the blanks for a new one. Hosts are not on the model yet, so they start empty either way. */
const valuesFrom = (mailbox?: Mailbox) => ({
    type: mailbox?.type ?? null,
    address: mailbox?.address ?? '',
    dailyLimit: mailbox?.daily_limit ?? 20,
    warmUp: mailbox ? mailbox.status === 'warming_up' : true,
});

/**
 * Adds a sending mailbox, or edits one when `mailbox` is given. Every box is
 * its own connection, so several Gmail accounts are fine: each one goes
 * through Google's account picker separately. Editing keeps the connection
 * type; only the limit, warm-up and (for IMAP) the credentials change.
 */
export function MailboxDialog({
    mailbox,
    trigger,
}: {
    mailbox?: Mailbox;
    /** Replaces the default "+ Mailbox" button. */
    trigger?: ReactNode;
}) {
    const initial = valuesFrom(mailbox);
    const editing = mailbox !== undefined;

    const [open, setOpen] = useState(false);
    const [step, setStep] = useState(0);
    const [type, setType] = useState<MailboxType | null>(initial.type);
    const [connected, setConnected] = useState(false);
    const [copied, setCopied] = useState(false);
    const [address, setAddress] = useState(initial.address);
    const [imapHost, setImapHost] = useState('');
    const [smtpHost, setSmtpHost] = useState('');
    const [password, setPassword] = useState('');
    const [dailyLimit, setDailyLimit] = useState(initial.dailyLimit);
    const [warmUp, setWarmUp] = useState(initial.warmUp);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // An existing box is already connected; its password stays unless a new one is typed.
    const ready = editing
        ? type === 'gmail' || address.trim() !== ''
        : type === 'gmail'
          ? connected && address.trim() !== ''
          : address.trim() !== '' &&
            imapHost.trim() !== '' &&
            smtpHost.trim() !== '' &&
            password.trim() !== '';

    // Hosts and password ride along for validation; the server stores them once the mail connection lands.
    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        const credentials = type === 'imap'
            ? { imap_host: imapHost, smtp_host: smtpHost, password }
            : {};
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (bag: Record<string, string>) => setErrors(bag),
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            router.patch(
                update.url(mailbox.id),
                { daily_limit: dailyLimit, warm_up: warmUp, ...credentials },
                options,
            );

            return;
        }

        router.post(
            store.url(),
            { type, address, daily_limit: dailyLimit, warm_up: warmUp, ...credentials },
            options,
        );
    };

    const toggle = (value: boolean) => {
        setOpen(value);
        setStep(0);
        setConnected(false);
        setCopied(false);
        setErrors({});

        // A cancelled edit must not linger into the next one.
        if (value && editing) {
            const values = valuesFrom(mailbox);
            setType(values.type);
            setAddress(values.address);
            setImapHost('');
            setSmtpHost('');
            setPassword('');
            setDailyLimit(values.dailyLimit);
            setWarmUp(values.warmUp);
        }
    };

    // The clipboard can be refused in some browsers; the link stays visible either way.
    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(MOCK_CONNECT_URL);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
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
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit mailbox' : 'New mailbox'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Change the limit or warm-up; reconnect if sending fails.'
                                : 'An address to send from. The sender picks whichever box still has room today, so add several to spread the load.'}
                        </DialogDescription>
                    </DialogHeader>

                    {!editing && (
                        <DialogSteps
                            steps={STEPS}
                            current={step}
                            onSelect={setStep}
                        />
                    )}

                    {editing ? (
                        <div className="grid gap-4">
                            {type === 'gmail' ? (
                                <div className="flex items-center gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-4 text-sm shadow-(--raised-shadow)">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-500/15 text-green-600 dark:text-green-400">
                                        <Check className="size-4" />
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="font-medium">
                                            Connected
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {address}
                                        </span>
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="shrink-0"
                                    >
                                        <RefreshCw />
                                        Reconnect
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    <Field label="Address" icon={AtSign} htmlFor="mailbox-address">
                                        <Input
                                            id="mailbox-address"
                                            type="email"
                                            value={address}
                                            onChange={(event) => setAddress(event.target.value)}
                                            placeholder="hallo@snelstack.io"
                                        />
                                    </Field>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="IMAP host" icon={Server} htmlFor="mailbox-imap">
                                            <Input
                                                id="mailbox-imap"
                                                value={imapHost}
                                                onChange={(event) => setImapHost(event.target.value)}
                                                placeholder="imap.example.com:993"
                                            />
                                        </Field>
                                        <Field label="SMTP host" icon={Server} htmlFor="mailbox-smtp">
                                            <Input
                                                id="mailbox-smtp"
                                                value={smtpHost}
                                                onChange={(event) => setSmtpHost(event.target.value)}
                                                placeholder="smtp.example.com:587"
                                            />
                                        </Field>
                                    </div>
                                    <Field label="App password" icon={KeyRound} htmlFor="mailbox-password">
                                        <Input
                                            id="mailbox-password"
                                            type="password"
                                            value={password}
                                            onChange={(event) => setPassword(event.target.value)}
                                            placeholder="Leave empty to keep the current one"
                                        />
                                    </Field>
                                </>
                            )}

                            <Limits
                                dailyLimit={dailyLimit}
                                setDailyLimit={setDailyLimit}
                                warmUp={warmUp}
                                setWarmUp={setWarmUp}
                            />
                        </div>
                    ) : step === 0 ? (
                        <div
                            role="radiogroup"
                            aria-label="Connection"
                            className="grid gap-2"
                        >
                            {types.map((option) => {
                                const active = type === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={active}
                                        onClick={() => setType(option.value)}
                                        className={cn(
                                            'flex items-start gap-3 rounded-lg border p-3 text-left transition-colors',
                                            active
                                                ? 'border-(--raised-border) bg-accent/40 shadow-(--raised-shadow)'
                                                : 'border-border hover:bg-accent/40',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-9 shrink-0 items-center justify-center rounded-md',
                                                active
                                                    ? 'bg-linear-to-br from-sky-400 to-violet-500 text-white'
                                                    : 'bg-accent text-muted-foreground',
                                            )}
                                        >
                                            <option.icon className="size-4" />
                                        </span>
                                        <span className="flex min-w-0 flex-col gap-0.5">
                                            <span className="text-sm font-medium">
                                                {option.label}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {option.description}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    ) : type === 'gmail' ? (
                        <div className="grid gap-4">
                            {/* Google's picker lets you choose or add an account, so several Gmails need no tricks. */}
                            <div className="flex flex-col gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-4 shadow-(--raised-shadow)">
                                {connected ? (
                                    // Mocked connect: Google would hand the address back; until then it is typed here.
                                    <div className="flex items-center gap-3 text-sm">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-500/15 text-green-600 dark:text-green-400">
                                            <Check className="size-4" />
                                        </span>
                                        <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                                            <span className="font-medium">
                                                Connected
                                            </span>
                                            <Input
                                                type="email"
                                                value={address}
                                                onChange={(event) => setAddress(event.target.value)}
                                                placeholder="loc@snelstack.com"
                                                aria-label="Gmail address"
                                                autoFocus
                                            />
                                            <FieldError message={errors.address} />
                                        </span>
                                    </div>
                                ) : (
                                    <>
                                        <Button
                                            type="button"
                                            onClick={() => setConnected(true)}
                                        >
                                            <Mail />
                                            Connect with Google
                                        </Button>
                                        <p className="text-xs text-muted-foreground">
                                            Google asks which account to use, so
                                            a second Gmail works from this same
                                            browser.
                                        </p>
                                    </>
                                )}
                            </div>

                            {!connected && (
                                <div className="flex flex-col gap-1.5">
                                    <span className="text-xs text-muted-foreground">
                                        Account open in another browser or
                                        profile? Paste the link there.
                                    </span>
                                    <div className="flex gap-1.5">
                                        <Input
                                            readOnly
                                            value={MOCK_CONNECT_URL}
                                            className="font-mono text-xs"
                                            aria-label="Connect link"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="shrink-0"
                                            onClick={copyLink}
                                            aria-label="Copy link"
                                        >
                                            {copied ? (
                                                <Check className="text-green-600 dark:text-green-400" />
                                            ) : (
                                                <Copy />
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            )}

                            <Limits
                                dailyLimit={dailyLimit}
                                setDailyLimit={setDailyLimit}
                                warmUp={warmUp}
                                setWarmUp={setWarmUp}
                            />
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            <Field label="Address" icon={AtSign} htmlFor="mailbox-address" error={errors.address}>
                                <Input
                                    id="mailbox-address"
                                    type="email"
                                    value={address}
                                    onChange={(event) => setAddress(event.target.value)}
                                    placeholder="hallo@snelstack.io"
                                    autoFocus
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="IMAP host" icon={Server} htmlFor="mailbox-imap">
                                    <Input
                                        id="mailbox-imap"
                                        value={imapHost}
                                        onChange={(event) => setImapHost(event.target.value)}
                                        placeholder="imap.example.com:993"
                                    />
                                </Field>
                                <Field label="SMTP host" icon={Server} htmlFor="mailbox-smtp">
                                    <Input
                                        id="mailbox-smtp"
                                        value={smtpHost}
                                        onChange={(event) => setSmtpHost(event.target.value)}
                                        placeholder="smtp.example.com:587"
                                    />
                                </Field>
                            </div>
                            <Field label="App password" icon={KeyRound} htmlFor="mailbox-password">
                                <Input
                                    id="mailbox-password"
                                    type="password"
                                    value={password}
                                    onChange={(event) => setPassword(event.target.value)}
                                    placeholder="An app password, not your login"
                                />
                            </Field>

                            <Limits
                                dailyLimit={dailyLimit}
                                setDailyLimit={setDailyLimit}
                                warmUp={warmUp}
                                setWarmUp={setWarmUp}
                            />
                        </div>
                    )}

                    <DialogFooter>
                        {editing ? (
                            <>
                                <Button
                                    key="cancel"
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button key="submit" type="submit" disabled={!ready || processing}>
                                    <Check />
                                    Save
                                </Button>
                            </>
                        ) : step === 0 ? (
                            <>
                                <Button
                                    key="cancel"
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    key="next"
                                    type="button"
                                    disabled={type === null}
                                    onClick={() => setStep(1)}
                                >
                                    Next
                                    <ChevronRight />
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button
                                    key="back"
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setStep(0)}
                                >
                                    <ChevronLeft />
                                    Back
                                </Button>
                                <Button key="submit" type="submit" disabled={!ready || processing}>
                                    <Plus />
                                    Add mailbox
                                </Button>
                            </>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Daily limit and warm-up, the same for both connection types. */
function Limits({
    dailyLimit,
    setDailyLimit,
    warmUp,
    setWarmUp,
}: {
    dailyLimit: number;
    setDailyLimit: (value: number) => void;
    warmUp: boolean;
    setWarmUp: (value: boolean) => void;
}) {
    return (
        <>
            <Field label="Daily limit" icon={Gauge} htmlFor="mailbox-limit">
                <Input
                    id="mailbox-limit"
                    type="number"
                    min={1}
                    max={200}
                    value={dailyLimit}
                    onChange={(event) => setDailyLimit(Number(event.target.value))}
                    className="w-32"
                />
            </Field>

            {/* A fresh address that sends 40 a day on day one lands in spam; the sender ramps the limit itself. */}
            <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-(--raised-border) bg-accent/40 p-3 shadow-(--raised-shadow)">
                <input
                    type="checkbox"
                    checked={warmUp}
                    onChange={(event) => setWarmUp(event.target.checked)}
                    className="mt-0.5 size-4 shrink-0 accent-violet-500"
                />
                <span className="flex min-w-0 flex-col gap-0.5">
                    <span className="flex items-center gap-1.5 text-sm">
                        <Flame className="size-4 shrink-0 text-muted-foreground" />
                        Warm up
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Start at 5 a day and grow to {dailyLimit} over two
                        weeks.
                    </span>
                </span>
            </label>
        </>
    );
}

function Field({
    label,
    icon: Icon,
    htmlFor,
    error,
    children,
}: {
    label: string;
    icon: typeof Mail;
    htmlFor?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label
                htmlFor={htmlFor}
                className="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Icon className="size-3.5 shrink-0" />
                {label}
            </Label>
            {children}
            <FieldError message={error} />
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-xs text-red-600 dark:text-red-400">{message}</p>;
}
