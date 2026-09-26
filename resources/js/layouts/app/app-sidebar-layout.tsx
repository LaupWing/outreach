import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    actions,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            {/* One viewport high, so a page can pin its header and footer and scroll only its table. */}
            <AppContent variant="sidebar" className="h-svh min-w-0 overflow-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} actions={actions} />
                {children}
            </AppContent>
        </AppShell>
    );
}
