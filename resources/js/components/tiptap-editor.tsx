import { EditorContent, EditorContext, useEditor } from '@tiptap/react';
import type { JSONContent } from '@tiptap/react';
import { Selection } from '@tiptap/extensions';
import { toast } from 'sonner';
import { ImageUploadNode } from '@/components/tiptap-node/image-upload-node/image-upload-node-extension';
import '@/components/tiptap-node/image-upload-node/image-upload-node.scss';
import '@/components/tiptap-styles';
import { BlockquoteButton } from '@/components/tiptap-ui/blockquote-button';
import { CodeBlockButton } from '@/components/tiptap-ui/code-block-button';
import { HeadingDropdownMenu } from '@/components/tiptap-ui/heading-dropdown-menu';
import { ImageAlignButtons } from '@/components/tiptap-ui/image-align-button';
import { ImageUploadButton } from '@/components/tiptap-ui/image-upload-button';
import { LinkPopover } from '@/components/tiptap-ui/link-popover';
import { ListDropdownMenu } from '@/components/tiptap-ui/list-dropdown-menu';
import { MarkButton } from '@/components/tiptap-ui/mark-button';
import { UndoRedoButton } from '@/components/tiptap-ui/undo-redo-button';
import {
    Toolbar,
    ToolbarGroup,
    ToolbarSeparator,
} from '@/components/tiptap-ui-primitive/toolbar';
import {
    getTiptapExtensions,
    IMAGE_ACCEPT,
    IMAGE_MAX_BYTES,
    uploadFeedbackImage,
} from '@/lib/tiptap';
import type { TiptapProfile } from '@/lib/tiptap';

interface TiptapEditorProps {
    profile: TiptapProfile;
    value?: JSONContent | null;
    onChange: (value: JSONContent) => void;
}

/**
 * Trình soạn theo giao diện template Tiptap "Simple Editor", nhưng chỉ bật
 * các nút ứng với whitelist dùng chung phía server
 * (App\Rules\ValidTiptapDocument) — bấm được là lưu được.
 */
export function TiptapEditor({ profile, value, onChange }: TiptapEditorProps) {
    const isFeedback = profile === 'feedback';

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            ...getTiptapExtensions({ resizeImages: true }),
            Selection,
            ImageUploadNode.configure({
                accept: IMAGE_ACCEPT,
                maxSize: IMAGE_MAX_BYTES,
                limit: 3,
                upload: uploadFeedbackImage,
                onError: (error) => toast.error(error.message),
            }),
        ],
        content: value ?? '',
        onUpdate: ({ editor }) => onChange(editor.getJSON()),
        editorProps: {
            attributes: {
                'aria-label': isFeedback
                    ? 'Nội dung góp ý'
                    : 'Nội dung trao đổi',
            },
        },
    });

    return (
        <div className="feedback-editor rounded-md border bg-background">
            <EditorContext.Provider value={{ editor }}>
                <Toolbar>
                    <ToolbarGroup>
                        <UndoRedoButton action="undo" />
                        <UndoRedoButton action="redo" />
                    </ToolbarGroup>

                    <ToolbarSeparator />

                    <ToolbarGroup>
                        <HeadingDropdownMenu modal={false} levels={[1, 2, 3]} />
                        <ListDropdownMenu
                            modal={false}
                            types={['bulletList', 'orderedList']}
                        />
                        <BlockquoteButton />
                        <CodeBlockButton />
                    </ToolbarGroup>

                    <ToolbarSeparator />

                    <ToolbarGroup>
                        <MarkButton type="bold" />
                        <MarkButton type="italic" />
                        <MarkButton type="strike" />
                        <MarkButton type="code" />
                        <MarkButton type="underline" />
                        <LinkPopover />
                    </ToolbarGroup>

                    <ToolbarSeparator />

                    <ToolbarGroup>
                        <ImageUploadButton text="Ảnh" />
                    </ToolbarGroup>
                    <ImageAlignButtons editor={editor} />
                </Toolbar>

                <EditorContent editor={editor} />
            </EditorContext.Provider>
        </div>
    );
}
