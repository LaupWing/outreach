import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-full bg-linear-to-br from-sky-400 to-violet-500">
                <AppLogoIcon className="size-4.5 fill-white stroke-white stroke-[1.5] [stroke-linejoin:round]" />
            </div>
            <div className="ml-1 flex-1 truncate text-left text-xl leading-none tracking-tight">
                <span className="bg-linear-to-r from-sky-400 to-violet-500 bg-clip-text font-bold text-transparent">
                    snel
                </span>
                <span className="font-serif italic">reach</span>
            </div>
        </>
    );
}
