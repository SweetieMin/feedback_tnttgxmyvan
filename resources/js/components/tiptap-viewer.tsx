import { EditorContent, useEditor } from '@tiptap/react';
import type { JSONContent } from '@tiptap/react';
import { useEffect } from 'react';
import '@/components/tiptap-styles';
import { getTiptapExtensions } from '@/lib/tiptap';
import { cn } from '@/lib/utils';

interface TiptapViewerProps {
    content: JSONContent;
    className?: string;
}

/**
 * Hiển thị nội dung Tiptap chỉ-đọc với schema dùng chung của trình soạn.
 */
export function TiptapViewer({ content, className }: TiptapViewerProps) {
    const editor = useEditor({
        extensions: getTiptapExtensions(),
        content,
        editable: false,
        immediatelyRender: false,
    });

    useEffect(() => {
        if (editor && content) {
            editor.commands.setContent(content);
        }
    }, [editor, content]);

    if (!editor) {
        return null;
    }

    return (
        <EditorContent
            editor={editor}
            className={cn('tiptap-viewer', className)}
        />
    );
}
