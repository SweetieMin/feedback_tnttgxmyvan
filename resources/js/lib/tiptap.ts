import StarterKit from '@tiptap/starter-kit';
import type { Extensions, JSONContent } from '@tiptap/react';
import FeedbackImageController from '@/actions/App/Http/Controllers/FeedbackImageController';
import {
    AlignedImage,
    ResizableImage,
} from '@/components/tiptap-node/image-node/resizable-image';

/**
 * Editor đã gõ rồi xoá hết vẫn trả về doc hợp lệ (một paragraph rỗng) nên
 * server không coi là thiếu — phải tự kiểm tra có nội dung thật hay không.
 * Một ảnh đứng riêng cũng tính là có nội dung.
 */
export function hasTextContent(node: JSONContent | null | undefined): boolean {
    if (!node) {
        return false;
    }

    if (node.type === 'image') {
        return true;
    }

    if (node.type === 'text') {
        return (node.text ?? '').trim() !== '';
    }

    return (node.content ?? []).some(hasTextContent);
}

export type TiptapProfile = 'feedback' | 'message';

const LINK_PROTOCOLS = ['http', 'https', 'mailto'];

export const IMAGE_ACCEPT = 'image/jpeg,image/png,image/gif,image/webp';

export const IMAGE_MAX_BYTES = 5 * 1024 * 1024;

export function getTiptapExtensions({ resizeImages = false } = {}): Extensions {
    const ImageExtension = resizeImages ? ResizableImage : AlignedImage;

    return [
        StarterKit.configure({
            horizontalRule: false,
            heading: { levels: [1, 2, 3] },
            link: {
                protocols: LINK_PROTOCOLS,
                autolink: true,
                openOnClick: false,
                enableClickSelection: true,
                HTMLAttributes: {
                    rel: 'noopener noreferrer nofollow',
                    target: '_blank',
                },
            },
        }),
        ImageExtension.configure({ allowBase64: false }),
    ];
}

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

/**
 * Tải ảnh lên server (dùng cho ImageUploadNode của Tiptap) và trả về đường
 * dẫn ảnh — dạng duy nhất server chấp nhận cho `image.src`.
 */
export function uploadFeedbackImage(
    file: File,
    onProgress?: (event: { progress: number }) => void,
    abortSignal?: AbortSignal,
): Promise<string> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const body = new FormData();
        body.append('image', file);

        request.open('POST', FeedbackImageController.store.url());
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-XSRF-TOKEN', xsrfToken());

        request.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress?.({
                    progress: Math.round((event.loaded / event.total) * 100),
                });
            }
        };

        request.onload = () => {
            const data = JSON.parse(request.responseText || '{}') as {
                url?: string;
                message?: string;
                errors?: { image?: string[] };
            };

            if (request.status === 201 && data.url) {
                resolve(data.url);

                return;
            }

            reject(
                new Error(
                    data.errors?.image?.[0] ??
                        (request.status === 429
                            ? 'Bạn tải ảnh quá nhanh, vui lòng thử lại sau ít phút.'
                            : 'Không tải được ảnh lên, vui lòng thử lại.'),
                ),
            );
        };

        request.onerror = () =>
            reject(new Error('Mất kết nối khi tải ảnh lên.'));
        request.onabort = () => reject(new Error('Đã huỷ tải ảnh.'));
        abortSignal?.addEventListener('abort', () => request.abort());

        request.send(body);
    });
}
