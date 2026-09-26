import { Link } from '@inertiajs/react';
import {
    House,
    Inbox,
    Mailbox,
    MessageSquare,
    Search,
    Tag,
    Target,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { dashboard } from '@/routes';
import type { NavGroup } from '@/types';

const mainNavGroups: NavGroup[] = [
    {
        items: [
            { title: 'Home', href: dashboard(), icon: House },
            { title: 'Inbox', icon: Inbox },
            { title: 'Search', icon: Search },
        ],
    },
    {
        label: 'Outreach',
        items: [
            { title: 'Leads', icon: Users },
            { title: 'Niches', icon: Target },
            { title: 'Offers', icon: Tag },
        ],
    },
    {
        label: 'Sending',
        items: [
            { title: 'Mailboxes', icon: Mailbox },
            { title: 'Messages', icon: MessageSquare },
        ],
    },
];

export function AppSidebar() {
    const { toggleSidebar } = useSidebar();

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="h-14 justify-center border-b border-sidebar-border px-3 transition-[height] ease-linear group-data-[collapsible=icon]:h-12 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center gap-1">
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        {/* Collapsing happens here; expanding through the chevron in the page header. */}
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <SidebarTrigger className="text-muted-foreground group-data-[collapsible=icon]:hidden" />
                            </TooltipTrigger>
                            <TooltipContent side="right">
                                Collapse sidebar
                            </TooltipContent>
                        </Tooltip>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0">
                <NavMain groups={mainNavGroups} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
