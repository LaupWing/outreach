import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavGroup } from '@/types';

// Only the active item is white and raised: hairline border, top highlight and a soft outer edge.
const menuButtonClassName =
    'h-9 border border-transparent font-medium text-muted-foreground hover:text-foreground data-[active=true]:border-(--raised-border) data-[active=true]:text-foreground data-[active=true]:shadow-(--raised-shadow)';

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {groups.map((group, index) => (
                <Fragment key={group.label ?? index}>
                    {/* Collapsed, the group labels are gone, so a line keeps the groups apart. */}
                    {index > 0 && (
                        <SidebarSeparator className="mx-0 hidden group-data-[collapsible=icon]:block" />
                    )}
                    <SidebarGroup className="px-2">
                        {group.label && (
                            <SidebarGroupLabel className="text-xs text-muted-foreground">
                                {group.label}
                            </SidebarGroupLabel>
                        )}
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    {item.href ? (
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isCurrentUrl(item.href)}
                                            tooltip={{ children: item.title }}
                                            className={menuButtonClassName}
                                        >
                                            <Link href={item.href} prefetch>
                                                <NavItemContent item={item} />
                                            </Link>
                                        </SidebarMenuButton>
                                    ) : item.onClick ? (
                                        <SidebarMenuButton
                                            onClick={item.onClick}
                                            tooltip={{ children: item.title }}
                                            className={menuButtonClassName}
                                        >
                                            <NavItemContent item={item} />
                                            {item.hint && (
                                                <kbd className="ml-auto rounded border border-border bg-accent px-1 font-sans text-[10px] text-muted-foreground group-data-[collapsible=icon]:hidden">
                                                    {item.hint}
                                                </kbd>
                                            )}
                                        </SidebarMenuButton>
                                    ) : (
                                        <SidebarMenuButton
                                            aria-disabled
                                            tooltip={{ children: item.title }}
                                            className={cn(
                                                menuButtonClassName,
                                                'opacity-50',
                                            )}
                                        >
                                            <NavItemContent item={item} />
                                        </SidebarMenuButton>
                                    )}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                </Fragment>
            ))}
        </>
    );
}

function NavItemContent({ item }: { item: NavGroup['items'][number] }) {
    return (
        <>
            {item.icon && <item.icon />}
            <span>{item.title}</span>
        </>
    );
}
