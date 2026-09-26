import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/** The raised violet band of the primary buttons, so a toast reads as the app confirming something. */
const raised =
    'shadow-[inset_0_1px_0_0_rgba(255,255,255,0.22),0_1px_2px_0_rgba(0,0,0,0.25),0_8px_24px_-8px_rgba(0,0,0,0.35)]';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            toastOptions={{
                unstyled: true,
                classNames: {
                    toast: `${raised} flex w-full items-center gap-2.5 rounded-lg border px-4 py-3 text-sm font-medium`,
                    default: 'border-violet-700 bg-violet-600 text-white',
                    success: 'border-violet-700 bg-violet-600 text-white',
                    info: 'border-violet-700 bg-violet-600 text-white',
                    warning: 'border-amber-600 bg-amber-500 text-amber-950',
                    error: 'border-red-700 bg-red-600 text-white',
                    icon: 'shrink-0 [&_svg]:size-4',
                    title: 'leading-5',
                    description: 'text-xs font-normal opacity-80',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };
