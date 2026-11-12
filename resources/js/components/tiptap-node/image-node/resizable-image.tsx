import { Image } from '@tiptap/extension-image';
import { NodeViewWrapper, ReactNodeViewRenderer } from '@tiptap/react';
import type { NodeViewProps } from '@tiptap/react';
import { useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';

export const IMAGE_ALIGNMENTS = ['left', 'center', 'right'] as const;

export const AlignedImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            'data-align': {
                default: null,
                parseHTML: (element) =>
                    IMAGE_ALIGNMENTS.find(
                        (align) => align === element.getAttribute('data-align'),
                    ) ?? null,
            },
        };
    },
});

function ResizableImageView({ node, updateAttributes }: NodeViewProps) {
    const image = useRef<HTMLImageElement>(null);
    const drag = useRef<{ x: number; width: number; scale: number } | null>(null);
    const [previewWidth, setPreviewWidth] = useState<number>();
    const alignment = node.attrs['data-align'];
    const resizeScale = alignment === 'center' ? 2 : 1;

    const clampWidth = (width: number) => {
        const available =
            image.current?.closest('.tiptap-resizable-image')?.clientWidth ??
            4000;
        return Math.min(
            Math.max(48, Math.round(width)),
            Math.max(1, available),
            4000,
        );
    };

    const saveWidth = (width: number) =>
        updateAttributes({ width: clampWidth(width), height: null });

    const startResize = (
        event: PointerEvent<HTMLButtonElement>,
        side: 'left' | 'right',
    ) => {
        if (!event.isPrimary || event.button !== 0 || !image.current) {
            return;
        }

        event.preventDefault();
        event.currentTarget.focus();
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = {
            x: event.clientX,
            width: image.current.getBoundingClientRect().width,
            scale: side === 'left' ? -resizeScale : resizeScale,
        };
    };

    const widthAt = (event: PointerEvent<HTMLButtonElement>) => {
        const start = drag.current;

        if (!event.isPrimary || !start) {
            return undefined;
        }

        return start.width + (event.clientX - start.x) * start.scale;
    };

    const resizeWithKeyboard = (
        event: KeyboardEvent<HTMLButtonElement>,
        side: 'left' | 'right',
    ) => {
        if (
            !image.current ||
            (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')
        ) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const outward = side === 'left' ? 'ArrowLeft' : 'ArrowRight';
        const step = event.key === outward ? 16 : -16;
        saveWidth(image.current.getBoundingClientRect().width + step);
    };

    return (
        <NodeViewWrapper
            className="tiptap-resizable-image"
            contentEditable={false}
            data-align={alignment}
        >
            <span className="tiptap-resizable-image-frame">
                <img
                    ref={image}
                    src={node.attrs.src}
                    alt={node.attrs.alt ?? ''}
                    title={node.attrs.title ?? undefined}
                    width={previewWidth ?? node.attrs.width ?? undefined}
                    height={node.attrs.height ?? undefined}
                />
                {(['left', 'right'] as const).map((side) => (
                    <button
                        key={side}
                        type="button"
                        className="tiptap-image-resize-handle"
                        data-side={side}
                        aria-label={
                            side === 'left'
                                ? 'Đổi kích thước ảnh từ mép trái'
                                : 'Đổi kích thước ảnh từ mép phải'
                        }
                        title="Kéo hoặc dùng phím mũi tên trái/phải để đổi kích thước ảnh"
                        onPointerDown={(event) => startResize(event, side)}
                        onPointerMove={(event) => {
                            const width = widthAt(event);

                            if (width !== undefined) {
                                setPreviewWidth(clampWidth(width));
                            }
                        }}
                        onPointerUp={(event) => {
                            const width = widthAt(event);

                            if (
                                width !== undefined &&
                                event.clientX !== drag.current?.x
                            ) {
                                saveWidth(width);
                            }
                        }}
                        onLostPointerCapture={() => {
                            drag.current = null;
                            setPreviewWidth(undefined);
                        }}
                        onKeyDown={(event) => resizeWithKeyboard(event, side)}
                    />
                ))}
            </span>
        </NodeViewWrapper>
    );
}

export const ResizableImage = AlignedImage.extend({
    addNodeView() {
        return ReactNodeViewRenderer(ResizableImageView);
    },
});
