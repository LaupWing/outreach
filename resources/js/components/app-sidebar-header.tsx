import { ChevronRight, CircleHelp } from 'lucide-react';
import type { ReactNode } from 'react';
import { AppearanceToggle } from '@/components/appearance-toggle';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    actions,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    /** Page-specific buttons, shown on the right like "+ Customer" and "Share" in the reference. */
    actions?: ReactNode;
}) {
    const { toggleSidebar } = useSidebar();

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border pr-6 pl-0 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:pr-4">
            {/* Collapsed, a full-height chevron strip against the sidebar expands it again. */}
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        onClick={toggleSidebar}
                        aria-label="Expand sidebar"
                        className="hidden h-full w-6 items-center justify-center border-r border-sidebar-border text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground group-has-data-[collapsible=icon]/sidebar-wrapper:md:flex"
                    >
                        <ChevronRight className="size-4" />
                    </button>
                </TooltipTrigger>
                <TooltipContent side="right">Expand sidebar</TooltipContent>
            </Tooltip>
            {/* On mobile the sidebar is a sheet, so this opens it. */}
            <SidebarTrigger className="ml-3 md:hidden" />

            <div className="flex items-center gap-2 pl-4 group-has-data-[collapsible=icon]/sidebar-wrapper:md:pl-3">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ml-auto flex items-center gap-2">
                {actions}
                {actions && (
                    <span className="mx-1 h-5 w-px bg-sidebar-border" />
                )}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-foreground"
                            aria-label="Help"
                        >
                            <CircleHelp className="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Help</TooltipContent>
                </Tooltip>
                <AppearanceToggle />
            </div>
        </header>
    );
}
