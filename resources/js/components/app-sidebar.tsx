import { Link, usePage  } from '@inertiajs/react';
import { 
    BookOpen, 
    FolderGit2, 
    LayoutGrid, 
    Users, 
    ScrollText, 
    Boxes, 
    FileSpreadsheet,
    Clock, 
    GraduationCap, 
    Library,
    CalendarDays,
    ClipboardCheck,
    CalendarRange,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    const navItems: NavItem[] = [...mainNavItems];
    navItems.push({ title: 'Fasilitas Lab', href: '/facilities', icon: Boxes });
    navItems.push({ title: 'Impor Mahasiswa', href: '/students/import', icon: FileSpreadsheet });
    navItems.push({ title: 'Pengajuan Jadwal', href: '/reservations', icon: CalendarDays });
    navItems.push({ title: 'Kalender Lab', href: '/calendar', icon: CalendarRange });
    
    if (auth.user.role === 'asisten_lab' || auth.user.role === 'ketua_lab') {
        navItems.push({ title: 'Antrean Persetujuan', href: '/approvals', icon: ClipboardCheck });
        navItems.push({ title: 'Mata Kuliah', href: '/courses', icon: Library });
        navItems.push({ title: 'Kelas', href: '/classes', icon: GraduationCap });
    }

    if (auth.user.role === 'ketua_lab') {
        navItems.push({ title: 'Kelola Akun', href: '/accounts', icon: Users });
        navItems.push({ title: 'Slot Waktu', href: '/time-slots', icon: Clock });
        navItems.push({ title: 'Audit Log', href: '/audit-logs', icon: ScrollText });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
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

            <SidebarContent>
                {/* <NavMain items={mainNavItems} /> */}
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
