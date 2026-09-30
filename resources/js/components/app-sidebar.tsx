import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    FilePlus,
    Handshake,
    LayoutGrid,
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
import { panel, resumen } from '@/routes';
import { index as convenios } from '@/routes/admin/convenios';
import { index as usuarios } from '@/routes/admin/usuarios';
import { create } from '@/routes/ventas';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const permisos = page.props.auth.permisos;

    const mainNavItems: NavItem[] = [
        ...(permisos?.verDashboard
            ? [{ title: 'Dashboard', href: resumen(), icon: LayoutGrid }]
            : []),
        ...(permisos?.registrarVentas
            ? [{ title: 'Registrar venta', href: create(), icon: FilePlus }]
            : []),
        ...(permisos?.verVentas
            ? [{ title: 'Panel de ventas', href: panel(), icon: ClipboardList }]
            : []),
        ...(permisos?.administrar
            ? [
                  { title: 'Usuarios', href: usuarios(), icon: Users },
                  { title: 'Convenios', href: convenios(), icon: Handshake },
              ]
            : []),
    ];

    const inicioUrl = mainNavItems[0]?.href ?? '/';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={inicioUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
