import { Form, Head, router } from '@inertiajs/react';
import { Eye, EyeOff, Pencil, Plus, Tags } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { AdminImageInput } from '@/components/admin-image-input';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
    is_active: boolean;
    feedbacks_count: number;
    avatar: string | null;
};

export default function AdminCategories({
    categories,
}: {
    categories: AdminCategory[];
}) {
    return (
        <>
            <Head title="Danh mục" />
            <div className="flex w-full min-w-0 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Danh mục"
                        description="Quản lý danh mục hiển thị khi gửi góp ý."
                    />
                    <CategoryDialog />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader className="bg-muted/50">
                            <TableRow>
                                <TableHead className="pl-4">Danh mục</TableHead>
                                <TableHead>Trạng thái</TableHead>
                                <TableHead className="text-right">
                                    Số góp ý
                                </TableHead>
                                <TableHead className="pr-4 text-right">
                                    Thao tác
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        Chưa có danh mục nào.
                                    </TableCell>
                                </TableRow>
                            )}
                            {categories.map((category) => (
                                <CategoryRow
                                    key={category.id}
                                    category={category}
                                />
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

AdminCategories.layout = {
    breadcrumbs: [{ title: 'Danh mục', href: index() }],
};

function CategoryRow({ category }: { category: AdminCategory }) {
    const [toggling, setToggling] = useState(false);

    const toggleActive = () => {
        setToggling(true);
        router.patch(
            CategoryController.update.url(category.id),
            { is_active: !category.is_active },
            { preserveScroll: true, onFinish: () => setToggling(false) },
        );
    };

    return (
        <TableRow className="h-14">
            <TableCell className="pl-4">
                <div className="flex items-center gap-3">
                    <Avatar className="size-9">
                        <AvatarImage
                            src={category.avatar ?? undefined}
                            alt=""
                            className="object-cover"
                        />
                        <AvatarFallback>
                            <Tags className="size-4" />
                        </AvatarFallback>
                    </Avatar>
                    <span className="font-medium">{category.name}</span>
                </div>
            </TableCell>
            <TableCell>
                <Badge variant={category.is_active ? 'secondary' : 'outline'}>
                    {category.is_active ? 'Đang dùng' : 'Đã ẩn'}
                </Badge>
            </TableCell>
            <TableCell className="text-right tabular-nums">
                {category.feedbacks_count}
            </TableCell>
            <TableCell className="pr-4">
                <div className="flex justify-end gap-1">
                    <CategoryDialog category={category} />
                    <Button
                        type="button"
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

function CategoryDialog({ category }: { category?: AdminCategory }) {
    const [open, setOpen] = useState(false);
    const [avatar, setAvatar] = useState<File | null>(null);
    const [cropping, setCropping] = useState(false);
    const title = category ? 'Sửa danh mục' : 'Thêm danh mục';

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    setAvatar(null);
                    setCropping(false);
                }
                setOpen(next);
            }}
        >
            <DialogTrigger asChild>
                {category ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Sửa ${category.name}`}
                        title={`Sửa ${category.name}`}
                    >
                        <Pencil />
                    </Button>
                ) : (
                    <Button type="button">
                        <Plus />
                        {title}
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Chọn ảnh và nhập tên danh mục.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        category
                            ? CategoryController.update.url(category.id)
                            : CategoryController.store.url()
                    }
                    method={category && !avatar ? 'patch' : 'post'}
                    options={{
                        preserveScroll: true,
                    }}
                    transform={(data) => ({
                        ...data,
                        ...(category && avatar ? { _method: 'PATCH' } : {}),
                        ...(avatar ? { avatar } : {}),
                    })}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <AdminImageInput
                                initial={category?.avatar}
                                label="Chọn ảnh danh mục"
                                error={errors.avatar}
                                onChange={setAvatar}
                                onPendingChange={setCropping}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="category-name">
                                    Tên danh mục
                                </Label>
                                <Input
                                    id="category-name"
                                    name="name"
                                    defaultValue={category?.name}
                                    required
                                    maxLength={100}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Huỷ
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing || cropping}
                                >
                                    {processing && <Spinner />}
                                    {category ? 'Lưu thay đổi' : 'Tạo danh mục'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
