import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            {/* The same wash as the home page, so logging in feels like the same place. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 top-0 h-[60svh] bg-[radial-gradient(45%_55%_at_50%_0%,rgb(139_92_246/0.14),transparent_70%),radial-gradient(30%_40%_at_70%_0%,rgb(56_189_248/0.1),transparent_70%)]"
            />

            <div className="relative w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-6">
                        <Link
                            href={home()}
                            className="flex items-center rounded-md"
                        >
                            <AppLogo />
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium text-balance">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-(--raised-border) bg-accent/30 p-6 shadow-(--raised-shadow)">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
