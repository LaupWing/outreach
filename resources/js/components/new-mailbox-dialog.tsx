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
import type { MailboxType } from '@/types';

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

/**
 * Adds a sending mailbox. Every box is its own connection, so several Gmail
 * accounts are fine: each one goes through Google's account picker separately.
 */
export function NewMailboxDialog() {
    const [open, setOpen] = useState(false);
    const [step, setStep] = useState(0);
    const [type, setType] = useState<MailboxType | null>(null);
    const [connected, setConnected] = useState(false);
    const [copied, setCopied] = useState(false);
    const [address, setAddress] = useState('');
    const [imapHost, setImapHost] = useState('');
    const [smtpHost, setSmtpHost] = useState('');
    const [password, setPassword] = useState('');
    const [dailyLimit, setDailyLimit] = useState(20);
    const [warmUp, setWarmUp] = useState(true);

    const ready =
        type === 'gmail'
            ? connected
            : address.trim() !== '' &&
              imapHost.trim() !== '' &&
              smtpHost.trim() !== '' &&
              password.trim() !== '';

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setOpen(false);
    };

    const toggle = (value: boolean) => {
        setOpen(value);
        setStep(0);
        setConnected(false);
        setCopied(false);
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
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground"
                >
                    <Plus />
                    Mailbox
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>New mailbox</DialogTitle>
                        <DialogDescription>
                            An address to send from. The sender picks whichever
                            box still has room today, so add several to spread
                            the load.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogSteps
                        steps={STEPS}
                        current={step}
                        onSelect={setStep}
                    />

                    {step === 0 ? (
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
                                    <div className="flex items-center gap-3 text-sm">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-500/15 text-green-600 dark:text-green-400">
                                            <Check className="size-4" />
                                        </span>
                                        <span className="flex min-w-0 flex-col">
                                            <span className="font-medium">
                                                Connected
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                loc@snelstack.com
                                            </span>
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
                            <Field label="Address" icon={AtSign} htmlFor="mailbox-address">
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
                        {step === 0 ? (
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
                                <Button key="submit" type="submit" disabled={!ready}>
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
    children,
}: {
    label: string;
    icon: typeof Mail;
    htmlFor?: string;
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
        </div>
    );
}
