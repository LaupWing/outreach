import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import {
    EnvelopeScene,
    type SceneStep,
} from '@/components/welcome/envelope-scene';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';

const STEPS: { title: string; heading: string; text: string; fact: string }[] =
    [
        {
            title: 'Find',
            heading: 'Search a niche in a city',
            text: 'Pick a niche and a place, like dentists in Utrecht. One Google Maps search brings back up to 20 businesses, with their site, phone and address.',
            fact: '1,000 free searches a month',
        },
        {
            title: 'Read',
            heading: 'The app reads their site',
            text: 'Every lead gets enriched: the email address from their contact page, and the text of the site, so the mail can be about them and not about you.',
            fact: 'Email and site text per lead',
        },
        {
            title: 'Write',
            heading: 'Claude fills in the offer',
            text: 'An offer is a sequence of mails with tags like {{hook}} and {{compliment}}. Claude reads the site and fills in every tag of every step in one go, so the follow-ups are ready too.',
            fact: 'Connected through MCP',
        },
        {
            title: 'Send',
            heading: 'Sent from your own mailboxes',
            text: 'Mail leaves from your own mailboxes within the sending hours, spread out and under each box’s daily limit. Nothing goes out in one burst.',
            fact: 'Daily limit per mailbox',
        },
        {
            title: 'Reply',
            heading: 'Replies land in one inbox',
            text: 'Replies and bounces are read from every mailbox. Follow-ups go out by themselves when they are due and stop as soon as someone answers.',
            fact: 'Auto follow-up per offer',
        },
    ];

/** Which section sits in the middle of the screen; that one drives the scene. */
function useActiveStep(count: number) {
    const refs = useRef<(HTMLElement | null)[]>([]);
    const [active, setActive] = useState<SceneStep>(0);

    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        setActive(
                            Number(
                                (entry.target as HTMLElement).dataset.step,
                            ) as SceneStep,
                        );
                    }
                }
            },
            { rootMargin: '-50% 0px -50% 0px' },
        );

        refs.current.slice(0, count).forEach((element) => {
            if (element) {
                observer.observe(element);
            }
        });

        return () => observer.disconnect();
    }, [count]);

    return {
        active,
        register: (index: number) => (element: HTMLElement | null) => {
            refs.current[index] = element;
        },
    };
}

export default function Welcome() {
    const { auth } = usePage().props;
    const { active, register: step } = useActiveStep(STEPS.length + 1);

    return (
        <>
            <Head title="Cold email that reads the site first" />

            <div className="relative min-h-svh bg-background text-foreground">
                {/* One wash of the brand colours behind the scene, nothing else. */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-0 top-0 h-[70svh] bg-[radial-gradient(50%_60%_at_75%_30%,rgb(139_92_246/0.14),transparent_70%),radial-gradient(35%_45%_at_90%_10%,rgb(56_189_248/0.1),transparent_70%)]"
                />

                <header className="sticky top-0 z-30 border-b border-transparent bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 md:px-8">
                        <Link href="/" className="flex items-center">
                            <AppLogo />
                        </Link>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild>
                                    <Link href={dashboard()}>Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button variant="ghost" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href={register()}>
                                            Get started
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="relative mx-auto grid max-w-6xl gap-x-12 px-4 md:px-8 lg:grid-cols-2">
                    {/* The scene: on top and sticky on phones, the right column on desktop. */}
                    <div className="sticky top-16 z-20 -mx-4 h-[34svh] bg-background/85 px-4 backdrop-blur md:-mx-8 md:px-8 lg:order-2 lg:mx-0 lg:h-[calc(100svh-4rem)] lg:bg-transparent lg:px-0 lg:backdrop-blur-none">
                        <div className="flex h-full flex-col items-center justify-center gap-6">
                            <EnvelopeScene
                                step={active}
                                className="h-full max-h-[26rem] w-full max-w-lg"
                            />
                            <ol className="hidden items-center gap-1 text-xs lg:flex">
                                {STEPS.map((item, index) => (
                                    <li
                                        key={item.title}
                                        className={cn(
                                            'rounded-full px-2.5 py-1 transition-colors duration-500',
                                            active === index + 1
                                                ? 'bg-accent text-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {item.title}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>

                    <div className="lg:order-1">
                        <section
                            ref={step(0)}
                            data-step={0}
                            className="flex min-h-[calc(66svh-4rem)] flex-col justify-center gap-6 py-12 lg:min-h-[calc(100svh-4rem)]"
                        >
                            <span className="text-sm font-medium text-violet-600 dark:text-violet-400">
                                Cold outreach for Dutch businesses
                            </span>
                            <h1 className="max-w-[16ch] text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                Cold email that{' '}
                                <span className="font-serif font-normal italic">
                                    reads their site
                                </span>{' '}
                                first.
                            </h1>
                            <p className="max-w-[52ch] text-base text-muted-foreground sm:text-lg">
                                Snelreach finds businesses on Google Maps,
                                reads their websites and sends your offer from
                                your own mailboxes, spread over the day. Claude
                                does the writing.
                            </p>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button size="lg" asChild>
                                    <Link
                                        href={
                                            auth.user ? dashboard() : register()
                                        }
                                    >
                                        {auth.user
                                            ? 'Open the dashboard'
                                            : 'Get started'}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                <span className="text-sm text-muted-foreground">
                                    Scroll to see how a mail is made
                                </span>
                            </div>
                        </section>

                        {STEPS.map((item, index) => (
                            <section
                                key={item.title}
                                ref={step(index + 1)}
                                data-step={index + 1}
                                className="flex min-h-[70svh] flex-col justify-center gap-4 py-12 lg:min-h-[85svh]"
                            >
                                <span className="flex items-center gap-2 text-sm font-medium text-muted-foreground tabular-nums">
                                    <span
                                        className={cn(
                                            'flex size-6 items-center justify-center rounded-full border text-xs transition-colors duration-500',
                                            active === index + 1
                                                ? 'border-violet-500 bg-violet-500 text-white'
                                                : 'border-border',
                                        )}
                                    >
                                        {index + 1}
                                    </span>
                                    {item.title}
                                </span>
                                <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                    {item.heading}
                                </h2>
                                <p className="max-w-[52ch] text-base text-muted-foreground sm:text-lg">
                                    {item.text}
                                </p>
                                <span className="w-fit rounded-md border border-(--raised-border) bg-accent/40 px-2.5 py-1 text-xs text-muted-foreground shadow-(--raised-shadow)">
                                    {item.fact}
                                </span>
                            </section>
                        ))}
                    </div>
                </main>

                <section className="relative border-t">
                    <div className="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-20 md:px-8 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex flex-col gap-3">
                            <h2 className="text-3xl font-semibold tracking-tight text-balance">
                                Start with one niche in one city.
                            </h2>
                            <p className="max-w-[52ch] text-muted-foreground">
                                Connect a mailbox, connect Claude, write one
                                offer. The first mails can go out today.
                            </p>
                        </div>
                        <Button size="lg" asChild>
                            <Link href={auth.user ? dashboard() : register()}>
                                {auth.user ? 'Open the dashboard' : 'Get started'}
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>
                </section>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-6xl px-4 py-6 text-xs text-muted-foreground md:px-8">
                        <span>
                            snelreach is made by{' '}
                            <a
                                href="https://snelstack.com"
                                className="underline underline-offset-4 hover:text-foreground"
                            >
                                snelstack
                            </a>
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}
