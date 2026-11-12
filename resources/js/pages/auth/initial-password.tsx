import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { update } from '@/routes/password/first';

export default function InitialPassword({
    passwordRules,
}: {
    passwordRules: string;
}) {
    return (
        <>
            <Head title="Đổi mật khẩu lần đầu" />
            <Form {...update.form()} className="grid gap-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="current-password">Mật khẩu tạm</Label>
                            <PasswordInput
                                id="current-password"
                                name="current_password"
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            <InputError message={errors.current_password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="new-password">Mật khẩu mới</Label>
                            <PasswordInput
                                id="new-password"
                                name="password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                required
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="confirm-password">Nhập lại mật khẩu mới</Label>
                            <PasswordInput
                                id="confirm-password"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Đổi mật khẩu và tiếp tục
                        </Button>
                    </>
                )}
            </Form>
            <Button asChild variant="ghost" className="w-full">
                <Link href={logout()} as="button">Đăng xuất</Link>
            </Button>
        </>
    );
}

InitialPassword.layout = {
    title: 'Đổi mật khẩu lần đầu',
    description: 'Đặt mật khẩu mới để tiếp tục vào cổng góp ý.',
};
