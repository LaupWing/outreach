import type { ReactNode } from 'react';
import {
    FilterTrigger,
    summarize,
    type FilterOption,
} from '@/components/filters/filter-trigger';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/** A plain dropdown for short, fixed lists such as status or source. Multiple options can be on. */
export function FilterMenu({
    label,
    icon,
    options,
    selected,
    onChange,
}: {
    label: string;
    icon?: ReactNode;
    options: FilterOption[];
    selected: string[];
    onChange: (selected: string[]) => void;
}) {
    const toggle = (value: string, checked: boolean) =>
        onChange(
            checked
                ? [...selected, value]
                : selected.filter((item) => item !== value),
        );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <FilterTrigger
                    label={label}
                    icon={icon}
                    active={selected.length > 0}
                >
                    {summarize(options, selected)}
                </FilterTrigger>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-44">
                {options.map((option) => (
                    <DropdownMenuCheckboxItem
                        key={option.value}
                        checked={selected.includes(option.value)}
                        onCheckedChange={(checked) =>
                            toggle(option.value, checked === true)
                        }
                        // Keep the menu open, so several options can be ticked in one go.
                        onSelect={(event) => event.preventDefault()}
                    >
                        {option.label}
                    </DropdownMenuCheckboxItem>
                ))}
                {selected.length > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onChange([])}
                            className="text-muted-foreground"
                        >
                            Clear
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
