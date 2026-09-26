import { Link } from '@inertiajs/react';
import {
    House,
    Inbox,
    Mailbox,
    MessageSquare,
    Radar,
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
            { title: 'Scrape', icon: Radar },
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
                <div className="flex items-center gap-1">
                    {/* A plain link, not a menu button: the icon-mode size overrides clipped the mark. */}
                    <Link
                        href={dashboard()}
                        prefetch
                        className="flex min-w-0 flex-1 items-center gap-2 rounded-md p-1 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0"
                    >
                        <AppLogo />
                    </Link>
                    {/* Collapsing happens here; expanding through the chevron in the page header. */}
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <SidebarTrigger className="text-muted-foreground group-data-[collapsible=icon]:hidden" />
                        </TooltipTrigger>
                        <TooltipContent side="right">
                            Collapse sidebar
                        </TooltipContent>
                    </Tooltip>
                </div>
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
