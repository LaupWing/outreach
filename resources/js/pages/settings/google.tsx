import { Form, Head, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/google';

type PageProps = {
    hasKey: boolean;
};

export default function Google() {
    const { hasKey } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Google settings" />

            <h1 className="sr-only">Google settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Google"
                    description="The Places API key the scraper searches with. Stored encrypted; the first 1,000 searches a month are free."
                />

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="google_places_key" className="flex items-center gap-1.5">
                                    <KeyRound className="size-3.5 text-muted-foreground" />
                                    Places API key
                                </Label>
                                <Input
                                    id="google_places_key"
                                    name="google_places_key"
                                    className="mt-1 block w-full font-mono text-xs"
                                    placeholder={hasKey ? 'A key is set; paste a new one to replace it' : 'AIza…'}
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.google_places_key} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Save</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Google.layout = {
    breadcrumbs: [
        {
            title: 'Google settings',
            href: edit(),
        },
    ],
};
