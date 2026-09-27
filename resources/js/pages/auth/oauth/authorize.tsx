import { Form, Head, usePage } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type PageProps = {
    client: { id: string; name: string };
    authToken: string;
    state: string | null;
    scopes: { id: string; description: string }[];
};

/** Consent for an MCP client: Claude asks once to act as this account. */
export default function Authorize() {
    const { client, authToken, state, scopes } = usePage<PageProps>().props;
    const { auth } = usePage().props;

    const hidden = (
        <>
            <input type="hidden" name="state" value={state ?? ''} />
            <input type="hidden" name="client_id" value={client.id} />
            <input type="hidden" name="auth_token" value={authToken} />
        </>
    );

    return (
        <>
            <Head title="Connect Claude" />

            <div className="flex flex-col gap-5">
                <p className="text-sm">
                    <span className="font-medium">{client.name}</span> wants to
                    work in Snelreach as{' '}
                    <span className="font-medium">{auth.user.email}</span>.
                </p>

                <ul className="flex flex-col gap-2 text-sm text-muted-foreground">
                    {scopes.length === 0 ? (
                        <li>
                            Find leads, write and send mail, read replies and
                            see stats, all on this account.
                        </li>
                    ) : (
                        scopes.map((scope) => (
                            <li
                                key={scope.id}
                                className="flex items-start gap-2"
                            >
                                <Check className="mt-0.5 size-4 shrink-0 text-violet-500" />
                                {scope.description}
                            </li>
                        ))
                    )}
                </ul>

                <p className="text-xs text-muted-foreground">
                    You can disconnect it any time from Settings → Connect
                    Claude.
                </p>

                <div className="flex gap-3">
                    <Form
                        action="/oauth/authorize"
                        method="delete"
                        className="flex-1"
                    >
                        {({ processing }) => (
                            <>
                                {hidden}
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <X />
                                    Cancel
                                </Button>
                            </>
                        )}
                    </Form>
                    <Form
                        action="/oauth/authorize"
                        method="post"
                        className="flex-1"
                    >
                        {({ processing }) => (
                            <>
                                {hidden}
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing ? <Spinner /> : <Check />}
                                    Allow
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

Authorize.layout = {
    title: 'Connect Claude',
    description: 'Give Claude access to this Snelreach account',
};
