import { Extension } from '@tiptap/core';
import { Fragment, Slice } from '@tiptap/pm/model';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import { toast } from 'sonner';
import {
    IMAGE_ACCEPT,
    IMAGE_MAX_BYTES,
    uploadFeedbackImage,
} from '@/lib/tiptap';

const pasteImagesKey = new PluginKey<DecorationSet>('pasteFeedbackImages');
const acceptedTypes = IMAGE_ACCEPT.split(',');

type PasteImagesAction = { id: object; position: number } | { id: object };

export const PasteFeedbackImages = Extension.create({
    name: 'pasteFeedbackImages',

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: pasteImagesKey,
                state: {
                    init: () => DecorationSet.empty,
                    apply(transaction, decorations) {
                        const mapped = decorations.map(
                            transaction.mapping,
                            transaction.doc,
                        );
                        const action = transaction.getMeta(pasteImagesKey) as
                            | PasteImagesAction
                            | undefined;

                        if (!action) {
                            return mapped;
                        }

                        if ('position' in action) {
                            const placeholder = Decoration.widget(
                                action.position,
                                () => {
                                    const element =
                                        document.createElement('span');
                                    element.setAttribute('role', 'status');
                                    element.textContent = 'Đang tải ảnh…';

                                    return element;
                                },
                                { id: action.id, side: -1 },
                            );

                            return mapped.add(transaction.doc, [placeholder]);
                        }

                        return mapped.remove(
                            mapped.find(
                                undefined,
                                undefined,
                                (spec) => spec.id === action.id,
                            ),
                        );
                    },
                },
                props: {
                    decorations: (state) => pasteImagesKey.getState(state),
                    handlePaste(view, event) {
                        const files = Array.from(
                            event.clipboardData?.files ?? [],
                        ).filter((file) => file.type.startsWith('image/'));

                        if (
                            files.length === 0 ||
                            view.state.selection.$from.parent.type.spec.code
                        ) {
                            return false;
                        }

                        if (
                            files.some(
                                (file) =>
                                    !acceptedTypes.includes(file.type) ||
                                    file.size > IMAGE_MAX_BYTES,
                            )
                        ) {
                            toast.error(
                                'Chỉ hỗ trợ ảnh JPEG, PNG, GIF hoặc WebP, tối đa 5 MB mỗi ảnh.',
                            );

                            return true;
                        }

                        const id = {};
                        view.dispatch(
                            view.state.tr.setMeta(pasteImagesKey, {
                                id,
                                position: view.state.selection.from,
                            }),
                        );

                        void Promise.allSettled(
                            files.map((file) => uploadFeedbackImage(file)),
                        ).then((results) => {
                            if (view.isDestroyed) {
                                return;
                            }

                            const [placeholder] =
                                pasteImagesKey
                                    .getState(view.state)
                                    ?.find(
                                        undefined,
                                        undefined,
                                        (spec) => spec.id === id,
                                    ) ?? [];

                            if (!placeholder) {
                                return;
                            }

                            const images = results.flatMap((result, index) => {
                                if (result.status === 'rejected') {
                                    toast.error(
                                        result.reason instanceof Error
                                            ? result.reason.message
                                            : 'Không tải được ảnh lên.',
                                    );

                                    return [];
                                }

                                return view.state.schema.nodes.image.create({
                                    src: result.value,
                                    alt: files[index].name.replace(
                                        /\.[^.]+$/,
                                        '',
                                    ),
                                });
                            });
                            const transaction = view.state.tr.setMeta(
                                pasteImagesKey,
                                { id },
                            );

                            if (images.length > 0) {
                                transaction.replaceRange(
                                    placeholder.from,
                                    placeholder.from,
                                    new Slice(Fragment.fromArray(images), 0, 0),
                                );
                            }

                            view.dispatch(transaction);
                        });

                        return true;
                    },
                },
            }),
        ];
    },
});
