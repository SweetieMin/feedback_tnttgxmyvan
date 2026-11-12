import { Form, Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, Copy, Pencil, Search, UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import UserRoleController from '@/actions/App/Http/Controllers/Admin/UserRoleController';
import { AdminImageInput } from '@/components/admin-image-input';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useInitials } from '@/hooks/use-initials';
import { useClipboard } from '@/hooks/use-clipboard';
import { index } from '@/routes/admin/users';
import type { UserRole } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    avatar: string | null;
};

const ROLE_OPTIONS: { value: UserRole; label: string }[] = [
    { value: 'employee', label: 'Nhân viên' },
    { value: 'handler', label: 'Người xử lý' },
    { value: 'admin', label: 'Quản trị viên' },
];

export default function AdminUsers({
    users,
    filters,
}: {
    users: AdminUser[];
    filters: { search?: string };
}) {
    const { auth, errors } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [savingId, setSavingId] = useState<number | null>(null);
    const getInitials = useInitials();

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            index.url(),
            search.trim() ? { search: search.trim() } : {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const clearSearch = () => {
        setSearch('');
        router.get(index.url(), {}, { preserveState: true, replace: true });
    };

    const changeRole = (user: AdminUser, role: UserRole) => {
        setSavingId(user.id);
        router.patch(
            UserRoleController.update.url(user.id),
            { role },
            { preserveScroll: true, onFinish: () => setSavingId(null) },
        );
    };

    return (
        <>
            <Head title="Người dùng & vai trò" />

            <div className="flex w-full min-w-0 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Người dùng & vai trò"
                        description="Người xử lý được nhận và xử lý góp ý trong hàng đợi; quản trị viên quản lý toàn bộ hệ thống."
                    />
                    <UserDialog onCreated={() => setSearch('')} />
                </div>

                {errors.role && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Không đổi được vai trò</AlertTitle>
                        <AlertDescription>{errors.role}</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submitSearch} className="flex flex-wrap gap-2">
                    <Input
                        type="search"
                        className="max-w-sm"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Tìm theo tên hoặc email"
                        aria-label="Tìm người dùng"
                    />
                    <Button type="submit" variant="outline">
                        <Search />
                        Tìm
                    </Button>
                    {filters.search && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={clearSearch}
                        >
                            Xoá tìm kiếm
                        </Button>
                    )}
                </form>

                {users.length === 0 ? (
                    <p className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                        Không tìm thấy người dùng nào.
                    </p>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader className="bg-muted/50">
                                <TableRow>
                                    <TableHead className="pl-4">Tên</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead className="w-52 pr-4">
                                        Vai trò
                                    </TableHead>
                                    <TableHead className="pr-4 text-right">
                                        Thao tác
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.map((user) => {
                                    const isSelf = user.id === auth.user.id;

                                    return (
                                        <TableRow
                                            key={user.id}
                                            className="h-14"
                                        >
                                            <TableCell className="pl-4">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-9">
                                                        <AvatarImage
                                                            src={
                                                                user.avatar ??
                                                                undefined
                                                            }
                                                            alt=""
                                                            className="object-cover"
                                                        />
                                                        <AvatarFallback>
                                                            {getInitials(
                                                                user.name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="font-medium">
                                                        {user.name}
                                                        {isSelf && (
                                                            <span className="ml-1 font-normal text-muted-foreground">
                                                                (Bạn)
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {user.email}
                                            </TableCell>
                                            <TableCell className="pr-4">
                                                <div className="flex items-center gap-2">
                                                    <Select
                                                        value={user.role}
                                                        disabled={
                                                            isSelf ||
                                                            savingId !== null
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            changeRole(
                                                                user,
                                                                value as UserRole,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className="w-40"
                                                            aria-label={`Vai trò của ${user.name}`}
                                                            title={
                                                                isSelf
                                                                    ? 'Bạn không thể tự đổi vai trò của chính mình.'
                                                                    : undefined
                                                            }
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {ROLE_OPTIONS.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    {savingId === user.id && (
                                                        <Spinner />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                <UserDialog user={user} />
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

function generateTemporaryPassword(): string {
    const groups = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%&*+-=',
    ];
    const alphabet = groups.join('');
    const random = new Uint32Array(1);
    const pickIndex = (length: number) => {
        const limit = 2 ** 32 - (2 ** 32 % length);

        do {
            crypto.getRandomValues(random);
        } while (random[0] >= limit);

        return random[0] % length;
    };
    const characters = groups.map((group) => group[pickIndex(group.length)]);

    while (characters.length < 20) {
        characters.push(alphabet[pickIndex(alphabet.length)]);
    }

    for (let index = characters.length - 1; index > 0; index--) {
        const other = pickIndex(index + 1);
        [characters[index], characters[other]] = [
            characters[other],
            characters[index],
        ];
    }

    return characters.join('');
}

function UserDialog({
    user,
    onCreated,
}: {
    user?: AdminUser;
    onCreated?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [avatar, setAvatar] = useState<File | null>(null);
    const [cropping, setCropping] = useState(false);
    const [password, setPassword] = useState('');
    const [copyMessage, setCopyMessage] = useState('');
    const [, copy] = useClipboard();
    const { auth } = usePage().props;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    setAvatar(null);
                    setCropping(false);
                    setCopyMessage('');
                    if (!user) setPassword(generateTemporaryPassword());
                } else {
                    setPassword('');
                }
                setOpen(next);
            }}
        >
            <DialogTrigger asChild>
                {user ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={`Sửa thông tin của ${user.name}`}
                        title={`Sửa thông tin của ${user.name}`}
                    >
                        <Pencil />
                    </Button>
                ) : (
                    <Button type="button">
                        <UserPlus />
                        Thêm người dùng
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {user ? 'Sửa người dùng' : 'Thêm người dùng'}
                    </DialogTitle>
                    <DialogDescription>
                        {user
                            ? `Cập nhật thông tin và ảnh đại diện của ${user.name}.`
                            : 'Người dùng sẽ phải đổi mật khẩu tạm ở lần đăng nhập đầu tiên.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        user
                            ? UserRoleController.updateDetails.url(user.id)
                            : UserRoleController.store.url()
                    }
                    method={user && !avatar ? 'patch' : 'post'}
                    transform={(data) => ({
                        ...data,
                        ...(user && avatar ? { _method: 'PATCH' } : {}),
                        ...(avatar ? { avatar } : {}),
                        ...(!user
                            ? { password, password_confirmation: password }
                            : {}),
                    })}
                    options={{
                        preserveScroll: Boolean(user),
                    }}
                    onSuccess={() => {
                        setPassword('');
                        setOpen(false);
                        if (!user) onCreated?.();
                    }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <AdminImageInput
                                initial={user?.avatar}
                                label="Chọn ảnh đại diện"
                                error={errors.avatar}
                                onChange={setAvatar}
                                onPendingChange={setCropping}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="user-name">Họ và tên</Label>
                                <Input
                                    id="user-name"
                                    name="name"
                                    defaultValue={user?.name}
                                    required
                                    maxLength={255}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-email">Email</Label>
                                <Input
                                    id="user-email"
                                    name="email"
                                    type="email"
                                    defaultValue={user?.email}
                                    required
                                    maxLength={255}
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div
                                className={
                                    user
                                        ? 'grid gap-4'
                                        : 'grid grid-cols-2 items-start gap-3 sm:gap-4'
                                }
                            >
                                {!user && (
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="new-user-password">
                                            Mật khẩu tạm
                                        </Label>
                                        <div className="flex min-w-0 gap-1">
                                            <Input
                                                id="new-user-password"
                                                value={password}
                                                readOnly
                                                autoComplete="off"
                                                className="min-w-0 font-mono"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                aria-label="Sao chép mật khẩu tạm"
                                                title="Sao chép mật khẩu tạm"
                                                disabled={
                                                    !password || processing
                                                }
                                                onClick={async () => {
                                                    const copied =
                                                        await copy(password);
                                                    setCopyMessage(
                                                        copied
                                                            ? 'Đã sao chép.'
                                                            : 'Hãy chọn mật khẩu và sao chép thủ công.',
                                                    );
                                                }}
                                            >
                                                <Copy />
                                            </Button>
                                        </div>
                                        <p
                                            role="status"
                                            className="empty:hidden text-xs text-muted-foreground"
                                        >
                                            {copyMessage}
                                        </p>
                                        <InputError message={errors.password} />
                                    </div>
                                )}
                                {user?.id !== auth.user.id && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="user-role">
                                            Vai trò
                                        </Label>
                                        <Select
                                            name="role"
                                            defaultValue={
                                                user?.role ?? 'employee'
                                            }
                                        >
                                            <SelectTrigger
                                                id="user-role"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {ROLE_OPTIONS.map(
                                                    ({ value, label }) => (
                                                        <SelectItem
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.role} />
                                    </div>
                                )}
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Huỷ
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        cropping ||
                                        (!user && !password)
                                    }
                                >
                                    {processing && <Spinner />}
                                    {user ? 'Lưu thay đổi' : 'Tạo tài khoản'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AdminUsers.layout = {
    breadcrumbs: [{ title: 'Người dùng & vai trò', href: index() }],
};
