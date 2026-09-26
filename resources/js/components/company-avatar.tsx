import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

// The colourful squares from the reference. Every stop keeps white text readable.
const gradients = [
    'from-violet-500 to-fuchsia-500',
    'from-sky-500 to-violet-500',
    'from-pink-500 to-orange-400',
    'from-emerald-500 to-cyan-500',
    'from-blue-500 to-indigo-600',
    'from-rose-500 to-pink-500',
    'from-amber-400 to-orange-500',
    'from-teal-500 to-sky-500',
];

/** Same name, same colour, every render. */
function gradientFor(name: string): string {
    let hash = 0;

    for (const character of name) {
        hash = (hash * 31 + character.charCodeAt(0)) >>> 0;
    }

    return gradients[hash % gradients.length];
}

export function CompanyAvatar({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex size-6 shrink-0 items-center justify-center rounded-md bg-linear-to-br text-[10px] font-semibold text-white',
                gradientFor(name),
                className,
            )}
        >
            {getInitials(name)}
        </span>
    );
}
