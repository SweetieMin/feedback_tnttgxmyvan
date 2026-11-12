import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { brandName } from '@/lib/brand';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-[#fffaf3] p-5 md:p-10 dark:bg-[#191312]">
            <div className="w-full max-w-md rounded-2xl border bg-background p-6 shadow-sm sm:p-8">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <AppLogoIcon className="size-18 rounded-full" />
                            <span className="text-center text-sm font-semibold text-primary">
                                {brandName}
                            </span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
