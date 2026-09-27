import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

/** The envelope stack on its own, for places where only the icon fits. */
export function AppLogoMark({ className }: { className?: string }) {
    return (
        <AppLogoIcon
            className={cn('aspect-square size-8 shrink-0', className)}
        />
    );
}

export default function AppLogo() {
    return (
        <>
            <AppLogoMark />
            <div className="ml-1 flex-1 truncate text-left text-xl leading-none tracking-tight group-data-[collapsible=icon]:hidden">
                <span className="bg-linear-to-r from-sky-400 to-violet-500 bg-clip-text font-bold text-transparent">
                    snel
                </span>
                <span className="font-serif italic">reach</span>
            </div>
        </>
    );
}
