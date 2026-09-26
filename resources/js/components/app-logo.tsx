import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

/** The round gradient mark on its own, for places where only the icon fits. */
export function AppLogoMark({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'flex aspect-square size-8 items-center justify-center rounded-full bg-linear-to-br from-sky-400 to-violet-500',
                className,
            )}
        >
            <AppLogoIcon className="size-4.5 fill-white stroke-white stroke-[1.5] [stroke-linejoin:round]" />
        </div>
    );
}

export default function AppLogo() {
    return (
        <>
            <AppLogoMark />
            <div className="ml-1 flex-1 truncate text-left text-xl leading-none tracking-tight">
                <span className="bg-linear-to-r from-sky-400 to-violet-500 bg-clip-text font-bold text-transparent">
                    snel
                </span>
                <span className="font-serif italic">reach</span>
            </div>
        </>
    );
}
