import { Check } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import {
    FilterTrigger,
    summarize,
    type FilterOption,
} from '@/components/filters/filter-trigger';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/** A searchable list for longer sets such as niche or city. Multiple options can be on. */
export function FilterCombobox({
    label,
    icon,
    options,
    selected,
    onChange,
    searchPlaceholder = 'Search…',
}: {
    label: string;
    icon?: ReactNode;
    options: FilterOption[];
    selected: string[];
    onChange: (selected: string[]) => void;
    searchPlaceholder?: string;
}) {
    const [open, setOpen] = useState(false);

    const toggle = (value: string) =>
        onChange(
            selected.includes(value)
                ? selected.filter((item) => item !== value)
                : [...selected, value],
        );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <FilterTrigger
                    label={label}
                    icon={icon}
                    active={selected.length > 0}
                >
                    {summarize(options, selected)}
                </FilterTrigger>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-56 p-0">
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>Nothing found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const checked = selected.includes(option.value);

                                return (
                                    <CommandItem
                                        key={option.value}
                                        value={option.label}
                                        onSelect={() => toggle(option.value)}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-4 items-center justify-center rounded-sm border',
                                                checked
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-muted-foreground/40',
                                            )}
                                        >
                                            {checked && (
                                                <Check className="size-3" />
                                            )}
                                        </span>
                                        {option.label}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                        {selected.length > 0 && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        value="__clear"
                                        onSelect={() => onChange([])}
                                        className="justify-center text-muted-foreground"
                                    >
                                        Clear
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
