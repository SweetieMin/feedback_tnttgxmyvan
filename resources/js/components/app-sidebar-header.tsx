import { Moon, Sun } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import {
    NotificationBell,
    PushNotificationToggle,
} from '@/components/notification-bell';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const nextAppearance = resolvedAppearance === 'dark' ? 'light' : 'dark';
    const themeLabel =
        nextAppearance === 'light'
            ? 'Chuyển sang giao diện sáng'
            : 'Chuyển sang giao diện tối';

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 shrink-0" />
                <div className="min-w-0 overflow-hidden">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="ml-auto flex shrink-0 items-center gap-2">
                <NotificationBell />
                <PushNotificationToggle />
                <Separator
                    orientation="vertical"
                    className="data-[orientation=vertical]:h-5"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={themeLabel}
                    title={themeLabel}
                    onClick={() => updateAppearance(nextAppearance)}
                >
                    {nextAppearance === 'light' ? <Sun /> : <Moon />}
                </Button>
            </div>
        </header>
    );
}
