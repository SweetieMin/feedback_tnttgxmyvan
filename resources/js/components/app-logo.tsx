import AppLogoIcon from '@/components/app-logo-icon';
import { brandName } from '@/lib/brand';

export default function AppLogo() {
    return (
        <>
            <div className="flex size-8 shrink-0 items-center justify-center">
                <AppLogoIcon className="size-8 rounded-full" />
            </div>
            <div className="ml-1 min-w-0 flex-1 text-left text-sm">
                <span className="block leading-tight font-semibold whitespace-normal">
                    {brandName}
                </span>
            </div>
        </>
    );
}
