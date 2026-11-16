import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PageSkeleton } from '@/components/page-skeleton';
import { useNavigationSkeleton } from '@/hooks/use-navigation-skeleton';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const skeleton = useNavigationSkeleton();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {skeleton && <PageSkeleton variant={skeleton} />}
                {/* Disembunyikan, bukan dibuang, supaya state halaman tidak hilang jika perpindahan gagal. */}
                <div className={skeleton ? 'hidden' : 'contents'}>{children}</div>
            </AppContent>
        </AppShell>
    );
}
