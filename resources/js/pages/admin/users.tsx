import { Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import UserRoleController from '@/actions/App/Http/Controllers/Admin/UserRoleController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { index } from '@/routes/admin/users';
import type { UserRole } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
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

            <div className="flex w-full max-w-4xl flex-col gap-6 p-4">
                <Heading
                    title="Người dùng & vai trò"
                    description="Người xử lý được nhận và xử lý góp ý trong hàng đợi; quản trị viên quản lý toàn bộ hệ thống."
                />

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
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">Tên</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead className="w-52 pr-4">
                                        Vai trò
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.map((user) => {
                                    const isSelf = user.id === auth.user.id;

                                    return (
                                        <TableRow key={user.id}>
                                            <TableCell className="pl-4 font-medium">
                                                {user.name}
                                                {isSelf && (
                                                    <span className="ml-1 font-normal text-muted-foreground">
                                                        (Bạn)
                                                    </span>
                                                )}
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

AdminUsers.layout = {
    breadcrumbs: [{ title: 'Người dùng & vai trò', href: index() }],
};
