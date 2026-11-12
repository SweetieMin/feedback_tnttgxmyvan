import { useForm } from '@inertiajs/react';
import type { UrlMethodPair } from '@inertiajs/core';
import type { JSONContent } from '@tiptap/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { TiptapEditor } from '@/components/tiptap-editor';
import { Button } from '@/components/ui/button';
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
import { hasTextContent } from '@/lib/tiptap';
import type { Category } from '@/types';

type FeedbackFormData = {
    title: string;
    category_id: string;
    content: JSONContent | null;
};

export function FeedbackForm({
    action,
    categories,
    submitLabel,
    initial,
    onCancel,
    onSuccess,
}: {
    action: UrlMethodPair;
    categories: Category[];
    submitLabel: string;
    initial?: { title: string; category_id: number; content: JSONContent };
    onCancel?: () => void;
    onSuccess?: () => void;
}) {
    const form = useForm<FeedbackFormData>({
        title: initial?.title ?? '',
        category_id: initial ? String(initial.category_id) : '',
        content: initial?.content ?? null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            content: hasTextContent(data.content) ? data.content : null,
        }));
        form.submit(action, { preserveScroll: true, onSuccess });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="title">Tiêu đề</Label>
                <Input
                    id="title"
                    value={form.data.title}
                    onChange={(event) => {
                        form.setData('title', event.target.value);
                        form.clearErrors('title');
                    }}
                    maxLength={255}
                    placeholder="Tóm tắt ngắn gọn góp ý của bạn"
                    aria-invalid={Boolean(form.errors.title)}
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="category_id">Danh mục</Label>
                <Select
                    value={form.data.category_id}
                    onValueChange={(value) => {
                        form.setData('category_id', value);
                        form.clearErrors('category_id');
                    }}
                >
                    <SelectTrigger
                        id="category_id"
                        className="w-full sm:w-72"
                        aria-invalid={Boolean(form.errors.category_id)}
                    >
                        <SelectValue placeholder="Chọn danh mục" />
                    </SelectTrigger>
                    <SelectContent>
                        {categories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={String(category.id)}
                            >
                                {category.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.category_id} />
            </div>

            <div className="grid gap-2">
                <Label>Nội dung</Label>
                <TiptapEditor
                    profile="feedback"
                    value={form.data.content}
                    onChange={(value) => {
                        form.setData('content', value);
                        form.clearErrors('content');
                    }}
                />
                <InputError message={form.errors.content} />
            </div>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {submitLabel}
                </Button>
                {onCancel && (
                    <Button type="button" variant="ghost" onClick={onCancel}>
                        Huỷ
                    </Button>
                )}
            </div>
        </form>
    );
}
