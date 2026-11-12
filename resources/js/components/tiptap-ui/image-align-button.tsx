import { NodeSelection } from '@tiptap/pm/state';
import { useEditorState } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import { AlignCenterIcon } from '@/components/tiptap-icons/align-center-icon';
import { AlignLeftIcon } from '@/components/tiptap-icons/align-left-icon';
import { AlignRightIcon } from '@/components/tiptap-icons/align-right-icon';
import { IMAGE_ALIGNMENTS } from '@/components/tiptap-node/image-node/resizable-image';
import { Button } from '@/components/tiptap-ui-primitive/button';
import { ToolbarGroup } from '@/components/tiptap-ui-primitive/toolbar';

const controls = {
    left: { Icon: AlignLeftIcon, label: 'Căn ảnh trái' },
    center: { Icon: AlignCenterIcon, label: 'Căn ảnh giữa' },
    right: { Icon: AlignRightIcon, label: 'Căn ảnh phải' },
};

export function ImageAlignButtons({ editor }: { editor: Editor | null }) {
    const selectedImage = useEditorState({
        editor,
        selector: ({ editor }) => {
            const selection = editor?.state.selection;

            if (
                !(selection instanceof NodeSelection) ||
                selection.node.type.name !== 'image'
            ) {
                return null;
            }

            return {
                position: selection.from,
                alignment: selection.node.attrs['data-align'] ?? 'left',
            };
        },
    });

    if (!editor || !selectedImage) {
        return null;
    }

    return (
        <ToolbarGroup aria-label="Căn ảnh">
            {IMAGE_ALIGNMENTS.map((alignment) => {
                const { Icon, label } = controls[alignment];
                const active = alignment === selectedImage.alignment;

                return (
                    <Button
                        key={alignment}
                        type="button"
                        variant="ghost"
                        aria-label={label}
                        aria-pressed={active}
                        data-active-state={active ? 'on' : 'off'}
                        tooltip={label}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .updateAttributes('image', {
                                    'data-align': alignment,
                                })
                                .setNodeSelection(selectedImage.position)
                                .run()
                        }
                    >
                        <Icon className="tiptap-button-icon" />
                    </Button>
                );
            })}
        </ToolbarGroup>
    );
}
