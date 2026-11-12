import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, MailCheck, Send } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Quên mật khẩu" />

            <div className="grid gap-5">
                {status && (
                    <Alert role="status" className="border-primary/20 bg-primary/5">
                        <MailCheck className="text-primary" />
                        <AlertTitle>Kiểm tra hộp thư của bạn</AlertTitle>
                        <AlertDescription>
                            Mở liên kết đặt lại mật khẩu trong email. Nếu chưa
                            thấy thư, hãy kiểm tra mục thư rác.
                        </AlertDescription>
                    </Alert>
                )}
                <Form {...email.form()} className="grid gap-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email tài khoản</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    required
                                    aria-invalid={Boolean(errors.email)}
                                    placeholder="email@example.com"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing ? <Spinner /> : <Send aria-hidden="true" />}
                                {status ? 'Gửi lại liên kết' : 'Gửi liên kết đặt lại mật khẩu'}
                            </Button>
                        </>
                    )}
                </Form>

                <div className="text-center text-sm">
                    <TextLink href={login()} className="inline-flex items-center gap-2">
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Quay lại đăng nhập
                    </TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Quên mật khẩu',
    description: 'Nhập email đã dùng để đăng nhập cổng góp ý.',
};
