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
} from '@/components/ui/sidebar';
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
    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="h-14 justify-center border-b border-sidebar-border px-3 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
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
