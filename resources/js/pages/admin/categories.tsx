import { Head, router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/admin/categories';

type AdminCategory = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    feedbacks_count: number;
};

export default function AdminCategories({
    categories,
}: {
    categories: AdminCategory[];
}) {
    return (
        <>
            <Head title="Danh mục" />

            <div className="flex w-full max-w-4xl flex-col gap-6 p-4">
                <Heading
                    title="Danh mục"
                    description="Danh mục giúp phân loại góp ý. Danh mục đã ẩn không còn xuất hiện khi gửi góp ý mới nhưng vẫn giữ nguyên trên các góp ý cũ."
                />

                <CreateCategoryForm />

                {categories.length === 0 ? (
                    <p className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                        Chưa có danh mục nào. Hãy tạo danh mục đầu tiên ở trên.
                    </p>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">Tên</TableHead>
                                    <TableHead className="w-28 text-right">
                                        Số góp ý
                                    </TableHead>
                                    <TableHead className="w-28">
                                        Trạng thái
                                    </TableHead>
                                    <TableHead className="w-48 pr-4 text-right">
                                        <span className="sr-only">
                                            Thao tác
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.map((category) => (
                                    <CategoryRow
                                        key={category.id}
                                        category={category}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

AdminCategories.layout = {
    breadcrumbs: [{ title: 'Danh mục', href: index() }],
};

function CreateCategoryForm() {
    const form = useForm({ name: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(CategoryController.store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-2">
            <Label htmlFor="new-category">Thêm danh mục</Label>
            <div className="flex flex-wrap gap-2">
                <Input
                    id="new-category"
                    className="max-w-sm"
                    value={form.data.name}
                    onChange={(event) => {
                        form.setData('name', event.target.value);
                        form.clearErrors('name');
                    }}
                    maxLength={100}
                    placeholder="Ví dụ: Cơ sở vật chất"
                    aria-invalid={Boolean(form.errors.name)}
                />
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner /> : <Plus />}
                    Thêm
                </Button>
            </div>
            <InputError message={form.errors.name} />
        </form>
    );
}

function CategoryRow({ category }: { category: AdminCategory }) {
    const [editing, setEditing] = useState(false);
    const [toggling, setToggling] = useState(false);
    const form = useForm({ name: category.name });

    const rename = (event: FormEvent) => {
        event.preventDefault();
        form.submit(CategoryController.update(category.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const cancelRename = () => {
        form.reset();
        form.clearErrors();
        setEditing(false);
    };

    const toggleActive = () => {
        setToggling(true);
        router.patch(
            CategoryController.update.url(category.id),
            { is_active: !category.is_active },
            { preserveScroll: true, onFinish: () => setToggling(false) },
        );
    };

    return (
        <TableRow>
            <TableCell className="pl-4 whitespace-normal">
                {editing ? (
                    <form onSubmit={rename} className="grid gap-1.5">
                        <div className="flex flex-wrap gap-2">
                            <Input
                                aria-label={`Tên mới cho ${category.name}`}
                                className="h-8 max-w-64"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                maxLength={100}
                                autoFocus
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <Button
                                type="submit"
                                size="sm"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                Lưu
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={cancelRename}
                            >
                                Huỷ
                            </Button>
                        </div>
                        <InputError message={form.errors.name} />
                    </form>
                ) : (
                    <span
                        className={
                            category.is_active
                                ? 'font-medium'
                                : 'font-medium text-muted-foreground'
                        }
                    >
                        {category.name}
                    </span>
                )}
            </TableCell>
            <TableCell className="text-right tabular-nums">
                {category.feedbacks_count}
            </TableCell>
            <TableCell>
                {category.is_active ? (
                    <Badge variant="secondary">Đang dùng</Badge>
                ) : (
                    <Badge variant="outline">Đã ẩn</Badge>
                )}
            </TableCell>
            <TableCell className="pr-4">
                <div className="flex justify-end gap-1">
                    {!editing && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil />
                            Đổi tên
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="ghost"
                        disabled={toggling}
                        onClick={toggleActive}
                    >
                        {toggling ? (
                            <Spinner />
                        ) : category.is_active ? (
                            <EyeOff />
                        ) : (
                            <Eye />
                        )}
                        {category.is_active ? 'Ẩn' : 'Hiện lại'}
                    </Button>
                </div>
            </TableCell>
        </TableRow>
    );
}
