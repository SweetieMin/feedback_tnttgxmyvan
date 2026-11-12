import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    Inbox,
    LayoutGrid,
    ListChecks,
    MessageSquareText,
    Tags,
    Users,
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
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminFeedbackIndex } from '@/routes/admin/feedback';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as feedbackIndex } from '@/routes/feedback';
import { index as queueIndex } from '@/routes/queue';
import type { NavItem, UserRole } from '@/types';

function mainNavItems(role: UserRole): NavItem[] {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Góp ý của tôi',
            href: feedbackIndex(),
            icon: MessageSquareText,
        },
    ];

    if (role === 'handler') {
        items.push({
            title: 'Hàng đợi xử lý',
            href: queueIndex(),
            icon: Inbox,
        });
    }

    if (role === 'admin') {
        items.push(
            {
                title: 'Tất cả góp ý',
                href: adminFeedbackIndex(),
                icon: ListChecks,
            },
            {
                title: 'Danh mục',
                href: adminCategoriesIndex(),
                icon: Tags,
            },
            {
                title: 'Người dùng & vai trò',
                href: adminUsersIndex(),
                icon: Users,
            },
        );
    }

    return items;
}

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
    const { auth } = usePage().props;

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
                <NavMain items={mainNavItems(auth.user.role)} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
