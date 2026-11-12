import type { ComponentProps } from 'react';
import { brandLogo } from '@/lib/brand';

export default function AppLogoIcon(props: ComponentProps<'img'>) {
    return (
        <img
            src={brandLogo}
            alt=""
            width={872}
            height={872}
            {...props}
        />
    );
}
