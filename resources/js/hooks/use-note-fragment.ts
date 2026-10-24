import { useEffect } from 'react';
import type { RefObject } from 'react';
import type { NoteEditorHandle } from '@/components/kite/note-editor';
import { scrollToHeading } from '@/components/kite/note-preview';
import type { OutlineItem } from '@/types';

/**
 * Go to the part of the note the address points at: a line (`#L12`), a block (`#^an-id`) or a
 * heading (`#a-heading`), in the preview when it's showing and the editor otherwise.
 */
export function useNoteFragment(
    editorRef: RefObject<NoteEditorHandle | null>,
    outline: OutlineItem[],
): void {
    useEffect(() => {
        const slug = window.location.hash.slice(1);
        const lineLink = /^L(\d+)$/.exec(slug);

        if (lineLink) {
            editorRef.current?.jumpToLine(Number(lineLink[1]));

            return;
        }

        const fragment = decodeURIComponent(slug);

        if (fragment.startsWith('^')) {
            if (!scrollToHeading(slug)) {
                editorRef.current?.jumpToBlock(fragment.slice(1));
            }

            return;
        }

        const line = outline.find((item) => item.slug === fragment)?.line;

        if (slug !== '' && !scrollToHeading(slug) && line !== undefined) {
            editorRef.current?.jumpToLine(line);
        }
    }, [editorRef, outline]);
}
