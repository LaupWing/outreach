import { Form, Link, usePage } from '@inertiajs/react';
import {
    Clock,
    KeyRound,
    Mailbox as MailboxIcon,
    Palette,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import { useState, type ComponentType } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import AppearanceToggleTab from '@/components/appearance-tabs';
import InputError from '@/components/input-error';
import { MailboxDialog } from '@/components/mailbox-dialog';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { setSettingsOpen, useSettingsOpen } from '@/hooks/use-settings-dialog';
import { cn } from '@/lib/utils';
import { update as updateGoogle } from '@/routes/google';
import { index as mailboxesIndex } from '@/routes/mailboxes';
import { edit as editSecurity } from '@/routes/security';
import { update as updateSending } from '@/routes/sending';

type Section = 'profile' | 'password' | 'appearance' | 'google' | 'mailboxes' | 'sending';

/** Enough for a Dutch agency and its clients; the field is free text for anything else. */
const timezones = [
    'Europe/Amsterdam',
    'Europe/Brussels',
    'Europe/Berlin',
    'Europe/London',
    'Europe/Madrid',
    'Europe/Paris',
    'UTC',
];

const sections: { id: Section; label: string; icon: ComponentType<{ className?: string }> }[] = [
    { id: 'profile', label: 'Profile', icon: UserRound },
    { id: 'password', label: 'Password', icon: ShieldCheck },
    { id: 'appearance', label: 'Appearance', icon: Palette },
    { id: 'google', label: 'Google', icon: KeyRound },
    { id: 'mailboxes', label: 'Mailboxes', icon: MailboxIcon },
    { id: 'sending', label: 'Sending', icon: Clock },
];

function SectionHeading({ title, description }: { title: string; description: string }) {
    return (
        <div className="grid gap-1">
            <h3 className="text-base font-semibold tracking-tight">{title}</h3>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

function ProfileSection() {
    const { auth } = usePage().props;

    return (
        <Form
            {...ProfileController.update.form()}
            options={{ preserveScroll: true, preserveState: true }}
            className="grid gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <SectionHeading title="Profile" description="Your name and the address you sign in with." />
                    <div className="grid gap-2">
                        <Label htmlFor="settings-name">Name</Label>
                        <Input id="settings-name" name="name" defaultValue={auth.user.name} required autoComplete="name" />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="settings-email">Email address</Label>
                        <Input id="settings-email" name="email" type="email" defaultValue={auth.user.email} required autoComplete="username" />
                        <InputError message={errors.email} />
                    </div>
                    <Button size="sm" className="justify-self-start" disabled={processing}>
                        Save
                    </Button>
                </>
            )}
        </Form>
    );
}

function PasswordSection() {
    return (
        <Form
            {...SecurityController.update.form()}
            options={{ preserveScroll: true, preserveState: true }}
            resetOnError={['password', 'password_confirmation', 'current_password']}
            resetOnSuccess
            className="grid gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <SectionHeading title="Password" description="A long, unique one keeps the account safe." />
                    <div className="grid gap-2">
                        <Label htmlFor="settings-current-password">Current password</Label>
                        <PasswordInput id="settings-current-password" name="current_password" autoComplete="current-password" />
                        <InputError message={errors.current_password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="settings-password">New password</Label>
                        <PasswordInput id="settings-password" name="password" autoComplete="new-password" />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="settings-password-confirmation">Repeat the new password</Label>
                        <PasswordInput id="settings-password-confirmation" name="password_confirmation" autoComplete="new-password" />
                        <InputError message={errors.password_confirmation} />
                    </div>
                    <Button size="sm" className="justify-self-start" disabled={processing}>
                        Change password
                    </Button>
                </>
            )}
        </Form>
    );
}

function AppearanceSection() {
    return (
        <div className="grid gap-5">
            <SectionHeading title="Appearance" description="Dark is the default; light and system follow your Mac." />
            <AppearanceToggleTab />
        </div>
    );
}

function GoogleSection() {
    const { auth } = usePage().props;

    return (
        <Form {...updateGoogle.form()} options={{ preserveScroll: true, preserveState: true }} resetOnSuccess className="grid gap-5">
            {({ processing, errors }) => (
                <>
                    <SectionHeading
                        title="Google Places"
                        description="The key the scraper searches with. Checked against Google before it is saved; stored encrypted. The first 1,000 searches a month are free."
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="settings-places-key">Places API key</Label>
                        <Input
                            id="settings-places-key"
                            name="google_places_key"
                            className="font-mono text-xs"
                            placeholder={auth.hasGoogleKey ? 'A key is set; paste a new one to replace it' : 'AIza…'}
                            autoComplete="off"
                        />
                        <InputError message={errors.google_places_key} />
                    </div>
                    <Button size="sm" className="justify-self-start" disabled={processing}>
                        Save
                    </Button>
                </>
            )}
        </Form>
    );
}

function MailboxesSection() {
    const { auth } = usePage().props;

    return (
        <div className="grid gap-5">
            <SectionHeading
                title="Mailboxes"
                description="The addresses you send from, over IMAP/SMTP with an app password. The sender spreads the load over every box with room."
            />
            <p className="text-sm">
                {auth.mailboxCount === 0
                    ? 'No mailbox yet.'
                    : `${auth.mailboxCount} ${auth.mailboxCount === 1 ? 'mailbox' : 'mailboxes'} connected.`}
            </p>
            <div className="flex items-center gap-2">
                <MailboxDialog
                    trigger={
                        <Button size="sm">
                            <MailboxIcon />
                            Add mailbox
                        </Button>
                    }
                />
                <Button size="sm" variant="outline" asChild>
                    <Link href={mailboxesIndex()} onClick={() => setSettingsOpen(false)} prefetch>
                        Manage mailboxes
                    </Link>
                </Button>
            </div>
        </div>
    );
}

function SendingSection() {
    const { auth } = usePage().props;
    const { send_timezone, send_from, send_until, send_weekdays_only } = auth.sending;
    const [weekdaysOnly, setWeekdaysOnly] = useState(send_weekdays_only);

    return (
        <Form {...updateSending.form()} options={{ preserveScroll: true, preserveState: true }} className="grid gap-5">
            {({ processing, errors }) => (
                <>
                    <SectionHeading
                        title="Sending hours"
                        description="Queued mail leaves between these hours, spread evenly over every mailbox with room. Outside them it waits for the next opening."
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="settings-timezone">Timezone</Label>
                        <Input
                            id="settings-timezone"
                            name="send_timezone"
                            list="settings-timezones"
                            defaultValue={send_timezone}
                            autoComplete="off"
                        />
                        <datalist id="settings-timezones">
                            {timezones.map((zone) => (
                                <option key={zone} value={zone} />
                            ))}
                        </datalist>
                        <InputError message={errors.send_timezone} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="settings-send-from">From</Label>
                            <Input id="settings-send-from" name="send_from" type="number" min={0} max={23} defaultValue={send_from} />
                            <InputError message={errors.send_from} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="settings-send-until">Until</Label>
                            <Input id="settings-send-until" name="send_until" type="number" min={1} max={24} defaultValue={send_until} />
                            <InputError message={errors.send_until} />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        {/* The checkbox is not a native input; the hidden field carries the value. */}
                        <input type="hidden" name="send_weekdays_only" value={weekdaysOnly ? '1' : '0'} />
                        <Checkbox
                            checked={weekdaysOnly}
                            onCheckedChange={(checked) => setWeekdaysOnly(checked === true)}
                        />
                        Weekdays only
                    </label>
                    <InputError message={errors.send_weekdays_only} />
                    <Button size="sm" className="justify-self-start" disabled={processing}>
                        Save
                    </Button>
                </>
            )}
        </Form>
    );
}

/** The settings as a dialog over whatever page is open, instead of pages of their own. */
export function SettingsDialog() {
    const open = useSettingsOpen();
    const [section, setSection] = useState<Section>('profile');

    return (
        <Dialog open={open} onOpenChange={setSettingsOpen}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-3xl">
                <div className="grid min-h-[30rem] sm:grid-cols-[13rem_1fr]">
                    <nav className="flex flex-col gap-1 border-b border-border bg-sidebar p-3 sm:border-r sm:border-b-0">
                        <DialogTitle className="px-3 pt-2 pb-3 text-sm font-semibold">Settings</DialogTitle>
                        <DialogDescription className="sr-only">Manage your account, keys and mailboxes.</DialogDescription>
                        {sections.map(({ id, label, icon: Icon }) => (
                            <button
                                key={id}
                                type="button"
                                onClick={() => setSection(id)}
                                aria-current={section === id ? 'page' : undefined}
                                className={cn(
                                    'flex h-9 items-center gap-2 rounded-md border border-transparent px-3 text-left text-sm font-medium text-muted-foreground hover:bg-sidebar-accent hover:text-foreground',
                                    section === id && 'border-(--raised-border) bg-sidebar-accent text-foreground shadow-(--raised-shadow)',
                                )}
                            >
                                <Icon className="size-4 shrink-0" />
                                {label}
                            </button>
                        ))}
                        {/* Two-factor asks for the password again first, which only works on a page of its own. */}
                        <Link
                            href={editSecurity()}
                            onClick={() => setSettingsOpen(false)}
                            className="mt-auto flex items-center gap-2 px-3 py-2 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <ShieldCheck className="size-3.5" />
                            Two-factor authentication
                        </Link>
                    </nav>
                    <div className="p-6 sm:p-8">
                        {section === 'profile' && <ProfileSection />}
                        {section === 'password' && <PasswordSection />}
                        {section === 'appearance' && <AppearanceSection />}
                        {section === 'google' && <GoogleSection />}
                        {section === 'mailboxes' && <MailboxesSection />}
                        {section === 'sending' && <SendingSection />}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
