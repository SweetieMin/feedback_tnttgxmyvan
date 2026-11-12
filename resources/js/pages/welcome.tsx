import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const destination = auth.user ? dashboard() : login();
    const action = auth.user ? 'Vào bảng điều khiển' : 'Đăng nhập';

    return (
        <div className="min-h-svh bg-[#fffaf3] text-foreground dark:bg-[#191312]">
            <Head title="Cổng góp ý" />

            <header className="border-b border-[#e9ded0] bg-background dark:border-border">
                <nav className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8" aria-label="Điều hướng chính">
                    <div className="flex min-w-0 items-center gap-2">
                        <AppLogo />
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={destination}>{action}</Link>
                    </Button>
                </nav>
            </header>

            <main className="mx-auto grid max-w-6xl items-center gap-10 px-5 py-12 sm:px-8 sm:py-20 lg:grid-cols-[1fr_0.95fr] lg:gap-16 lg:py-24">
                <div className="max-w-xl">
                    <p className="mb-4 text-sm font-semibold text-primary">
                        Xứ đoàn Giu-se Đặng Đình Viên
                    </p>
                    <h1 className="text-4xl leading-[1.12] font-semibold tracking-tight sm:text-6xl">
                        Cổng góp ý
                        <span className="mt-2 block text-primary">TNTT Mỹ Vân</span>
                    </h1>
                    <p className="mt-6 max-w-md text-base leading-relaxed text-muted-foreground sm:text-lg">
                        Gửi góp ý về sinh hoạt của xứ đoàn và theo dõi phản hồi từ ban phụ trách.
                    </p>
                    <Button asChild size="lg" className="mt-8">
                        <Link href={destination}>
                            {action}
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                <div className="grid min-h-72 place-items-center rounded-3xl border border-[#eddfca] bg-[#f8ead1] p-10 sm:min-h-96 dark:border-[#503426] dark:bg-[#33221b]">
                    <AppLogoIcon
                        className="aspect-square w-full max-w-68 rounded-full shadow-xl shadow-[#75420c]/15"
                        alt="Huy hiệu Đoàn TNTT GX Mỹ Vân"
                    />
                </div>
            </main>
        </div>
    );
}
