import { Head } from '@inertiajs/react';
import FeedbackController from '@/actions/App/Http/Controllers/FeedbackController';
import { FeedbackForm } from '@/components/feedback-form';
import Heading from '@/components/heading';
import { create, index } from '@/routes/feedback';
import type { Category } from '@/types';

export default function FeedbackCreate({
    categories,
}: {
    categories: Category[];
}) {
    return (
        <>
            <Head title="Gửi góp ý" />

            <div className="w-full max-w-3xl p-4">
                <Heading
                    title="Gửi góp ý"
                    description="Góp ý của bạn sẽ được chuyển tới đội xử lý. Bạn có thể sửa nội dung khi góp ý chưa được tiếp nhận."
                />

                {categories.length === 0 ? (
                    <p className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                        Chưa có danh mục nào đang hoạt động. Vui lòng liên hệ
                        quản trị viên.
                    </p>
                ) : (
                    <FeedbackForm
                        action={FeedbackController.store()}
                        categories={categories}
                        submitLabel="Gửi góp ý"
                    />
                )}
            </div>
        </>
    );
}

FeedbackCreate.layout = {
    breadcrumbs: [
        { title: 'Góp ý của tôi', href: index() },
        { title: 'Gửi góp ý', href: create() },
    ],
};
